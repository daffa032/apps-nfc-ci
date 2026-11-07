<?php

declare(strict_types=1);

namespace App\Services\Cards;

use App\Repositories\AccountRepository;
use App\Repositories\CardAliasRepository;
use App\Repositories\ProfileRepository;
use App\Repositories\SocialLinkRepository;
use App\Repositories\TemplateRepository;
use App\Services\Users\AccountStatus;
use DateTimeInterface;

/**
 * Decides what a visitor sees at /{username}: the card, a suspended notice, an inactive notice,
 * or nothing (404). Only an active card returns profile data (PRD EX-01).
 */
final class PublicCardService
{
    public const SHOW = 'show';
    public const SUSPENDED = 'suspended';
    public const INACTIVE = 'inactive';
    public const NOT_FOUND = 'not_found';
    public const MOVED = 'moved';

    private const CHANNELS = ['instagram', 'facebook', 'twitter', 'tiktok', 'website'];

    public function __construct(
        private ?ProfileRepository $profiles = null,
        private ?SocialLinkRepository $links = null,
        private ?SubscriptionAccessService $access = null,
        private ?CardAliasRepository $aliases = null,
        private ?AccountRepository $accounts = null,
        private ?TemplateRepository $templates = null,
        private ?BrandAssetService $brand = null
    ) {
        $this->profiles ??= new ProfileRepository();
        $this->links ??= new SocialLinkRepository();
        $this->access ??= new SubscriptionAccessService();
        $this->aliases ??= new CardAliasRepository();
        $this->accounts ??= new AccountRepository();
        $this->templates ??= new TemplateRepository();
        $this->brand ??= new BrandAssetService(access: $this->access);
    }

    /**
     * MOVED carries `username`: an old username (card_aliases) redirects to the current URL, so
     * printed QR codes and NFC tags keep working after a rename (LOG-14).
     *
     * @return array{state: string, view?: string, data?: array<string, mixed>, username?: string}
     */
    public function forUsername(string $username, DateTimeInterface $now): array
    {
        $user = $this->profiles->findAccountByUsername($username);
        if (!$user) {
            $aliasOf = $this->aliases->userIdForAlias($username);
            $current = $aliasOf === null ? null : $this->accounts->find($aliasOf);

            return $current ? ['state' => self::MOVED, 'username' => (string) $current['username']] : ['state' => self::NOT_FOUND];
        }
        if (AccountStatus::isSuspended($user)) {
            return ['state' => self::SUSPENDED];
        }
        if (!$this->access->isCardPublic($user, $now)) {
            return ['state' => self::INACTIVE];
        }

        $profile = $this->profiles->findForUser((int) $user['id_user']);
        $card = $profile ? $this->present($user, $profile, (string) ($profile['templates'] ?? '')) : null;

        return $card === null ? ['state' => self::NOT_FOUND] : ['state' => self::SHOW] + $card;
    }

    /**
     * Only Personal Info controls the public card language. Legacy profiles without a choice
     * use Indonesian, matching the form's default, never the viewer's dashboard cookie or GeoIP.
     */
    public static function cardLocale(array $profile): string
    {
        $locale = $profile['card_locale'] ?? null;

        return in_array($locale, config('Locale')->supported, true) ? $locale : 'id';
    }

    /**
     * Data for the Pro share page (PRD QR-02/04): same access rules as the card, plus an active Pro
     * plan. MOVED sends an old username to the canonical share URL; other states never carry profile
     * data. The QR always encodes the canonical card URL, never the share page, a token or contacts.
     *
     * @return array{state: string, username?: string, data?: array<string, mixed>}
     */
    public function shareFor(string $username, DateTimeInterface $now): array
    {
        $card = $this->forUsername($username, $now);
        if ($card['state'] !== self::SHOW) {
            return $card;
        }
        $user = $card['data']['user'];
        if ($this->access->planCode($user, $now) !== 'pro') {
            // Basic cards keep their share-link button; the QR page is a Pro feature.
            return ['state' => self::NOT_FOUND];
        }
        $p = $card['data']['dataPersonal'];

        return ['state' => self::SHOW, 'data' => [
            'cardLocale' => $card['data']['cardLocale'],
            'username' => (string) $user['username'],
            'name' => trim((string) ($p['nama'] ?? '')),
            'jobTitle' => (string) ($p['job_title'] ?? ''),
            'company' => trim((string) ($p['company_name'] ?? '')),
            'photo' => $p['profile_image'],
            'cardUrl' => $card['data']['shareUrl'],
            'qr' => \App\Libraries\QrCode::encode($card['data']['shareUrl']),
        ]];
    }

    /**
     * Card view and data for one account in a given catalog layout. Used by the public card and by
     * the owner's template preview, so both render the same markup. Null for a file outside the
     * catalog.
     *
     * @return array{view: string, data: array<string, mixed>}|null
     */
    public function present(array $user, array $profile, string $templateFile): ?array
    {
        $view = TemplateCatalog::viewFor($templateFile);
        if ($view === null) {
            return null;
        }
        $segments = [];
        $proDesign = null;
        $proSource = TemplateCatalog::proSourcePath($templateFile);
        if ($proSource !== null) {
            // Built-in Pro design: the docs source, re-checked by the sanitizer like any upload.
            $html = is_file($proSource) ? (string) file_get_contents($proSource) : '';
            if ($html === '') {
                return null;
            }
            $segments = (new TemplateHtmlSanitizer())->segments($html);
            $proDesign = substr((string) TemplateCatalog::layoutFor($templateFile), 4);
        } elseif (TemplateCatalog::isCustom($templateFile)) {
            // Re-checked on every render, so a stored version can never bypass the sanitizer.
            $html = $this->templates->currentHtml((int) substr($templateFile, strlen(TemplateCatalog::CUSTOM_PREFIX)));
            if ($html === null) {
                return null;
            }
            $segments = (new TemplateHtmlSanitizer())->segments($html);
            // An upload built on a Pro design gets that design's look (assets/cards/css/pro.css).
            $proDesign = TemplateHtmlSanitizer::proDesign((new TemplateHtmlSanitizer())->normalize($html));
        }

        $profile['phone_number'] = CardPresentation::phone($profile['no_hp'] ?? null);
        $profile['no_whatsapp'] = CardPresentation::phone(($profile['no_whatsapp'] ?? null) ?: ($profile['no_hp'] ?? null));
        $profile['email'] = filter_var($profile['email'] ?? '', FILTER_VALIDATE_EMAIL) ? $profile['email'] : '';
        $profile['google_map_link'] = CardPresentation::webUrl($profile['google_map_link'] ?? null);
        $profile['profile_image'] = CardPresentation::imageName($profile['profile_image'] ?? null);
        $profile['job_title'] = trim((string) ($profile['job_title'] ?? ''));

        $images = [];
        foreach ($this->profiles->galleryForProfile((int) $profile['id_data']) as $image) {
            $image['images'] = CardPresentation::imageName($image['images'] ?? null);
            // A blank caption renders no caption box at all, so whitespace counts as empty.
            $image['caption'] = trim((string) ($image['caption'] ?? ''));
            $images[] = $image;
        }

        $stored = $this->profiles->socialLinksForProfile((int) $profile['id_data']) ?? [];
        $social = [];
        foreach (self::CHANNELS as $channel) {
            $social[$channel] = CardPresentation::webUrl($stored[$channel] ?? null);
        }

        if ($proDesign !== null) {
            $view = 'digital_card/pro';
        }

        return ['view' => $view, 'data' => [
            'layout' => $proDesign !== null ? 'pro-' . $proDesign : TemplateCatalog::layoutFor($templateFile),
            // Public card language is independent of the dashboard/visitor locale.
            'cardLocale' => self::cardLocale($profile),
            'user' => $user,
            'dataPersonal' => $profile,
            'images' => $images,
            'socialMediaLinks' => $social,
            'galleryColumns' => CardPresentation::galleryColumns($profile),
            'vcard' => CardPresentation::vcard($profile, $social),
            // Canonical public URL, never the preview or an alias (PRD SH-02).
            'shareUrl' => base_url(rawurlencode((string) $user['username'])),
            'segments' => $segments,
            'proDesign' => $proDesign,
            // Brand logo / header background for this design, only while the account is Pro (BR-01).
            'brand' => $this->brand->forCard($user, $profile, $templateFile, new \DateTimeImmutable('now')),
        ]];
    }
}
