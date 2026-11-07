<?php

declare(strict_types=1);

namespace App\Services\Cards;

use App\Exceptions\FieldException;
use App\Repositories\AccountRepository;
use App\Repositories\BrandAssetRepository;
use App\Repositories\ProfileRepository;
use App\Repositories\TemplateRepository;
use App\Services\Uploads\PublicImageStorage;
use CodeIgniter\HTTP\Files\UploadedFile;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use Throwable;

/**
 * Brand logo and header background for the owner's current Pro design (PRD BR-01). Rules live here:
 * only an active Pro plan may upload, only slots the current design offers are accepted (Rona and
 * Sorot refuse a header background even through a direct POST), and a failed save keeps the old
 * files. After a downgrade the files stay private and are simply not rendered.
 */
final class BrandAssetService
{
    public const FOLDER = 'brand_assets';

    public function __construct(
        private ?ProfileRepository $profiles = null,
        private ?BrandAssetRepository $assets = null,
        private ?PublicImageStorage $storage = null,
        private ?SubscriptionAccessService $access = null,
        private ?AccountRepository $accounts = null,
        private ?TemplateRepository $templates = null,
    ) {
        $this->profiles ??= new ProfileRepository();
        $this->assets ??= new BrandAssetRepository();
        $this->storage ??= new PublicImageStorage();
        $this->access ??= new SubscriptionAccessService();
        $this->accounts ??= new AccountRepository();
        $this->templates ??= new TemplateRepository();
    }

    /**
     * Assets the card may show for this profile and design: empty unless the account is Pro now, the
     * design has the slot and the owner has not switched the asset off.
     *
     * logo_hidden tells the card that the owner switched an uploaded logo off, so the logo slot stays
     * empty instead of showing the company initials.
     *
     * @return array{brand_logo: ?string, header_background: ?string, logo_hidden: bool}
     */
    public function forCard(array $account, array $profile, string $templateFile, DateTimeInterface $now): array
    {
        $empty = ['brand_logo' => null, 'header_background' => null, 'logo_hidden' => false];
        $slots = $this->slotsFor($templateFile);
        if ($slots === [] || $this->access->planCode($account, $now) !== 'pro') {
            return $empty;
        }
        $row    = $this->assets->find((int) $profile['id_data'], $templateFile) ?? [];
        $assets = $empty;

        foreach ($slots as $slot) {
            $assets[$slot] = ($row[$slot . '_visible'] ?? true) ? CardPresentation::imageName($row[$slot] ?? null) : null;
        }
        $assets['logo_hidden'] = in_array('brand_logo', $slots, true) && ! ($row['brand_logo_visible'] ?? true) && CardPresentation::imageName($row['brand_logo'] ?? null) !== null;

        return $assets;
    }

    /**
     * Brand slots of a design: fixed for built-ins (TemplateCatalog), and for an uploaded template
     * the brand placeholders its current version contains.
     *
     * @return list<string>
     */
    private function slotsFor(string $templateFile): array
    {
        if (! TemplateCatalog::isCustom($templateFile)) {
            return TemplateCatalog::brandSlots($templateFile);
        }
        $html = $this->templates->currentHtml((int) substr($templateFile, strlen(TemplateCatalog::CUSTOM_PREFIX))) ?? '';

        return array_values(array_filter(['brand_logo', 'header_background'], static fn (string $slot): bool => str_contains($html, '{{' . $slot . '}}')));
    }

    /**
     * Pro design ("aurora" …) behind a template file: the built-in layout, or the design an uploaded
     * template is built on; null for every other layout.
     */
    private function designFor(string $templateFile): ?string
    {
        if (TemplateCatalog::isCustom($templateFile)) {
            $html = $this->templates->currentHtml((int) substr($templateFile, strlen(TemplateCatalog::CUSTOM_PREFIX))) ?? '';

            try {
                return $html === '' ? null : TemplateHtmlSanitizer::proDesign((new TemplateHtmlSanitizer())->normalize($html));
            } catch (FieldException) {
                return null;
            }
        }
        $layout = (string) TemplateCatalog::layoutFor($templateFile);

        return str_starts_with($layout, 'pro-') ? substr($layout, 4) : null;
    }

    /**
     * Stored assets of the owner's current design, for the Personal Info controls.
     *
     * @return array{slots: list<string>, isPro: bool, templateName: string, logoFrame: array{shape: string, ratio: int}, brand_logo: ?string, header_background: ?string, visible: array{brand_logo: bool, header_background: bool}}
     */
    public function pageFor(int $userId, DateTimeInterface $now): array
    {
        $account  = $this->accounts->find($userId);
        $profile  = $this->profiles->findForUser($userId);
        $template = (string) ($profile['templates'] ?? '');
        $row      = $profile ? ($this->assets->find((int) $profile['id_data'], $template) ?? []) : [];

        $templateName = '';

        foreach ($this->templates->all() as $catalogRow) {
            if (($catalogRow['nama_file'] ?? null) === $template) {
                $templateName = (string) $catalogRow['nama_template'];
            }
        }

        return [
            'slots'             => $this->slotsFor($template),
            'templateName'      => $templateName,
            'isPro'             => $account !== null && $this->access->planCode($account, $now) === 'pro',
            'logoFrame'         => TemplateCatalog::logoFrame($this->designFor($template)),
            'brand_logo'        => CardPresentation::imageName($row['brand_logo'] ?? null),
            'header_background' => CardPresentation::imageName($row['header_background'] ?? null),
            'visible'           => [
                'brand_logo'        => (bool) ($row['brand_logo_visible'] ?? true),
                'header_background' => (bool) ($row['header_background_visible'] ?? true),
            ],
        ];
    }

    /**
     * Saves uploaded/removed assets and the show/hide switches for the owner's current design. Each
     * new file is stored first; the old file is deleted only after the row points to the new one.
     * Hiding an asset keeps its file, so it can be shown again without a new upload.
     *
     * @param array<string, ?UploadedFile> $files   keyed by slot
     * @param list<string>                 $remove  slots to clear
     * @param array<string, bool>          $visible slot => shown on the card (slots left out keep their switch)
     *
     * @return list<string> slots that changed
     */
    public function save(int $userId, array $files, array $remove, DateTimeInterface $now, array $visible = []): array
    {
        $account = $this->accounts->find($userId);
        $profile = $this->profiles->findForUser($userId);
        if ($account === null || $profile === null) {
            throw new FieldException('brand_logo', lang('Profile.accountMissing'));
        }
        $template = (string) ($profile['templates'] ?? '');
        $slots    = $this->slotsFor($template);

        $profileId = (int) $profile['id_data'];
        $old       = $this->assets->find($profileId, $template) ?? [];
        // Only a switch that actually flips counts as a change.
        $switches = [];

        foreach ($visible as $slot => $show) {
            if ((bool) ($old[$slot . '_visible'] ?? true) !== $show) {
                $switches[$slot] = $show;
            }
        }

        $requested = array_values(array_unique(array_merge(
            array_keys(array_filter($files, static fn (?UploadedFile $f): bool => $f !== null && $f->getError() !== UPLOAD_ERR_NO_FILE)),
            $remove,
            array_keys($switches),
        )));
        if ($requested === []) {
            return [];
        }

        foreach ($requested as $slot) {
            if (! in_array($slot, $slots, true)) {
                throw new FieldException($slot, lang('Profile.brandSlotMissing'));
            }
        }
        if ($this->access->planCode($account, $now) !== 'pro') {
            throw new FieldException($requested[0], lang('Profile.brandProOnly'));
        }

        $changes = [];
        $stored  = [];

        try {
            foreach ($slots as $slot) {
                $file = $files[$slot] ?? null;
                if ($file !== null && $file->getError() !== UPLOAD_ERR_NO_FILE) {
                    try {
                        $stored[$slot] = $changes[$slot] = $this->storage->store($file, self::FOLDER);
                    } catch (InvalidArgumentException $exception) {
                        throw new FieldException($slot, $exception->getMessage());
                    }
                } elseif (in_array($slot, $remove, true)) {
                    $changes[$slot] = null;
                }
            }
            $fields = $changes;

            foreach ($switches as $slot => $show) {
                $fields[$slot . '_visible'] = $show;
            }
            $this->assets->save($profileId, $template, $fields, (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s'));
        } catch (Throwable $exception) {
            foreach ($stored as $name) {
                $this->storage->delete(self::FOLDER, $name);
            }

            throw $exception;
        }

        foreach (array_keys($changes) as $slot) {
            $previous = CardPresentation::imageName($old[$slot] ?? null);
            if ($previous !== null) {
                $this->storage->delete(self::FOLDER, $previous);
            }
        }

        return array_values(array_unique([...array_keys($changes), ...array_keys($switches)]));
    }
}
