<?php

declare(strict_types=1);

namespace App\Services\Cards;

use App\Repositories\SubscriptionRepository;
use DateTimeInterface;
use DateTimeZone;

/**
 * One rule for "is this account's card active", used by login, the `auth` filter, the
 * `subscription` filter, the Dashboard and the public card (PRD EX-01, EX-02).
 *
 * Source: the subscriptions table, periods [starts_at, ends_at) in UTC. A legacy date of
 * 10 October was imported as ending at 11 October 00:00 WIB, so it stays active through the
 * last WIB day. Staff roles do not depend on a subscription.
 */
final class SubscriptionAccessService
{
    public const STAFF_ROLES = ['Admin', 'Super Admin'];

    public function __construct(private ?SubscriptionRepository $subscriptions = null)
    {
        $this->subscriptions ??= new SubscriptionRepository();
    }

    public function isStaff(array $account): bool
    {
        return in_array($account['access_level'] ?? '', self::STAFF_ROLES, true);
    }

    /**
     * Whether the account may use card features (profile, templates, uploads, public card).
     */
    public function isActive(array $account, DateTimeInterface $now): bool
    {
        return $this->isStaff($account) || $this->currentPeriod($account, $now) !== null;
    }

    /**
     * Whether the public card for this account may be shown to visitors.
     */
    public function isCardPublic(array $account, DateTimeInterface $now): bool
    {
        return $this->isActive($account, $now);
    }

    /**
     * The period covering $now, with plan_code and plan_label, or null.
     */
    public function currentPeriod(array $account, DateTimeInterface $now): ?array
    {
        $userId = (int) ($account['id_user'] ?? 0);

        return $userId > 0 ? $this->subscriptions->currentForUser($userId, self::utc($now)) : null;
    }

    /**
     * Plan that decides feature rights now (themes, statistics): the current period's plan, 'pro'
     * for staff so they can try every feature, or null without an active period.
     */
    public function planCode(array $account, DateTimeInterface $now): ?string
    {
        if ($this->isStaff($account)) {
            return 'pro';
        }
        $period = $this->currentPeriod($account, $now);

        return $period === null ? null : (string) ($period['plan_code'] ?? 'basic');
    }

    /**
     * The period that ends last, even if it already ended, or null when the account never had one.
     */
    public function latestPeriod(array $account): ?array
    {
        $userId = (int) ($account['id_user'] ?? 0);

        return $userId > 0 ? $this->subscriptions->latestForUser($userId) : null;
    }

    public static function utc(DateTimeInterface $time): string
    {
        return \DateTimeImmutable::createFromInterface($time)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
