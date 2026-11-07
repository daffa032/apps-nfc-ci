<?php

declare(strict_types=1);

namespace App\Services\Cards;

use App\Repositories\AccountRepository;
use App\Repositories\ProfileRepository;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Everything the owner's Dashboard and billing page show: account, card profile, and the
 * subscription period from SubscriptionAccessService.
 */
final class DashboardService
{
    // Shown when the account has never had a period; every legacy period was imported as Basic.
    private const DEFAULT_PLAN_LABEL = 'Basic';

    public function __construct(
        private ?AccountRepository $accounts = null,
        private ?ProfileRepository $profiles = null,
        private ?SubscriptionAccessService $access = null,
    ) {
        $this->accounts ??= new AccountRepository();
        $this->profiles ??= new ProfileRepository();
        $this->access ??= new SubscriptionAccessService();
    }

    /**
     * @return array<string, mixed>|null null when the account no longer exists
     */
    public function summaryFor(int $userId, DateTimeInterface $now): ?array
    {
        $account = $userId > 0 ? $this->accounts->find($userId) : null;
        if (! $account) {
            return null;
        }

        $isStaff  = $this->access->isStaff($account);
        $period   = $this->access->currentPeriod($account, $now) ?? $this->access->latestPeriod($account);
        $expiry   = subscription_last_day($period['ends_at'] ?? null);
        $daysLeft = $expiry === null ? null : legacy_expiry_days_left($expiry, $now);
        $active   = $this->access->isActive($account, $now);

        $profile = $this->profiles->findForUser($userId);
        $name    = trim((string) ($profile['nama'] ?? ''));

        return [
            'isStaff'        => $isStaff,
            'isActive'       => $active,
            'neverActivated' => ! $isStaff && $expiry === null,
            'planCode'       => (string) ($period['plan_code'] ?? 'basic'),
            'planLabel'      => (string) ($period['plan_label'] ?? self::DEFAULT_PLAN_LABEL),
            'expiryLabel'    => $this->expiryLabel($expiry, $daysLeft),
            'remainingDays'  => remaining_days_label($expiry?->format('Y-m-d'), $now),
            'hasProfile'     => $profile !== null,
            'displayName'    => $name !== '' ? $name : (string) $account['username'],
            'firstName'      => $name !== '' ? explode(' ', $name)[0] : (string) $account['username'],
            'initials'       => $this->initials($name !== '' ? $name : (string) $account['username']),
            'company'        => trim((string) ($profile['company_name'] ?? '')),
            'profileImage'   => CardPresentation::imageName($profile['profile_image'] ?? null),
            'publicUrl'      => base_url(rawurlencode((string) $account['username'])),
        ];
    }

    private function expiryLabel(?DateTimeImmutable $expiry, ?int $daysLeft): string
    {
        if ($expiry === null) {
            return lang('Home.neverActivated');
        }

        return lang($daysLeft >= 0 ? 'Home.activeUntil' : 'Home.endedOn', [format_date_id($expiry)]);
    }

    private function initials(string $name): string
    {
        // First letter or digit of the first two words; markup or symbols in a name never become initials.
        preg_match_all('/(?<![\p{L}\p{N}])[\p{L}\p{N}]/u', strip_tags($name), $matches);
        $letters = mb_strtoupper(implode('', array_slice($matches[0], 0, 2)));

        return $letters !== '' ? $letters : '?';
    }
}
