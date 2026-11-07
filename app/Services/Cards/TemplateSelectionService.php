<?php

declare(strict_types=1);

namespace App\Services\Cards;

use App\Repositories\AccountRepository;
use App\Repositories\ProfileRepository;
use App\Repositories\TemplateRepository;
use DateTimeImmutable;
use DomainException;
use RuntimeException;

/**
 * Template choice per plan (PRD TH-01): an owner may pick an active template their current plan
 * allows. Basic gets Basic templates; Pro gets every active template. A template the card already
 * uses keeps rendering even after it is deactivated, and the server rejects any other choice.
 */
final class TemplateSelectionService
{
    public function __construct(
        private ?TemplateRepository $templates = null,
        private ?ProfileRepository $profiles = null,
        private ?AccountRepository $accounts = null,
        private ?SubscriptionAccessService $access = null,
    ) {
        $this->templates ??= new TemplateRepository();
        $this->profiles ??= new ProfileRepository();
        $this->accounts ??= new AccountRepository();
        $this->access ??= new SubscriptionAccessService();
    }

    /**
     * Catalog for the owner's Templates page, or null without a profile. Each row carries `allowed`.
     *
     * @return array{selectedTemplate: ?string, planCode: string, templates: list<array>}|null
     */
    public function pageFor(int $userId): ?array
    {
        $profile = $userId > 0 ? $this->profiles->findForUser($userId) : null;
        if (! $profile) {
            return null;
        }

        $selected  = $profile['templates'] ?? null;
        $planCode  = $this->planCodeFor($userId);
        $templates = [];

        foreach (TemplateCatalog::availableRows($this->templates->all()) as $template) {
            $isSelected = $template['nama_file'] === $selected;
            if (! $isSelected && ! self::isActive($template)) {
                continue;
            }
            $template['images_template'] = CardPresentation::imageName($template['images_template'] ?? null);
            $template['allowed']         = $isSelected || self::planAllows($planCode, $template);
            $templates[]                 = $template;
        }
        // Stable: only the selected template moves; the rest keep catalog order.
        usort($templates, static fn (array $a, array $b): int => (int) ($b['nama_file'] === $selected) <=> (int) ($a['nama_file'] === $selected));

        return ['selectedTemplate' => $selected, 'planCode' => $planCode, 'templates' => $templates];
    }

    public function select(int $userId, int $templateId): void
    {
        $template = $templateId > 0 ? $this->templates->find($templateId) : null;
        if (! $template || TemplateCatalog::viewFor($template['nama_file'] ?? null) === null || ! self::isActive($template)) {
            throw new DomainException(lang('Templates.invalid'));
        }

        $profile = $userId > 0 ? $this->profiles->findForUser($userId) : null;
        if (! $profile) {
            throw new MissingProfileException(lang('Templates.needProfile'));
        }
        if (! self::planAllows($this->planCodeFor($userId), $template)) {
            throw new PlanRequiredException(lang('Templates.proOnly'));
        }

        if (! $this->profiles->saveTemplateForUser($userId, (string) $template['nama_file'])) {
            throw new RuntimeException(lang('Templates.selectFailed'));
        }
    }

    /**
     * Owner's own card rendered in another layout, for the preview dialog (TH-01: preview never
     * changes the saved template). Only templates shown on the owner's Templates page can be
     * previewed, including Pro ones for a Basic owner so they can see what they would get.
     *
     * @return array{view: string, data: array<string, mixed>, template: array}|null
     */
    public function previewFor(int $userId, int $templateId, PublicCardService $cards): ?array
    {
        $page     = $this->pageFor($userId);
        $template = null;

        foreach ($page['templates'] ?? [] as $row) {
            if ((int) $row['id_template'] === $templateId) {
                $template = $row;
            }
        }
        $account = $template ? $this->accounts->find($userId) : null;
        $profile = $account ? $this->profiles->findForUser($userId) : null;
        $card    = $profile ? $cards->present($account, $profile, (string) $template['nama_file']) : null;

        return $card === null ? null : $card + ['template' => $template];
    }

    /**
     * The owner's card in the design they use now, for the live preview on Personal Info (PRD PV-01).
     * Same renderer as the public card; null when there is no profile or the design is unknown.
     */
    public function currentPreviewFor(int $userId, PublicCardService $cards): ?array
    {
        $account = $userId > 0 ? $this->accounts->find($userId) : null;
        $profile = $account ? $this->profiles->findForUser($userId) : null;

        return $profile ? $cards->present($account, $profile, (string) ($profile['templates'] ?? '')) : null;
    }

    public static function isActive(array $template): bool
    {
        return (int) ($template['is_active'] ?? 1) === 1;
    }

    public static function planAllows(string $planCode, array $template): bool
    {
        return $planCode === 'pro' || ($template['plan_code'] ?? 'basic') === 'basic';
    }

    /**
     * Plan of the current period; staff accounts are treated as Pro so they can preview every layout.
     */
    private function planCodeFor(int $userId): string
    {
        $account = $this->accounts->find($userId) ?? ['id_user' => $userId];

        return $this->access->planCode($account, new DateTimeImmutable('now')) ?? 'basic';
    }
}
