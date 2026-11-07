<?php

declare(strict_types=1);

namespace App\Services\Cards;

use App\Repositories\SubscriptionRepository;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use DomainException;

/**
 * Manual grants by a Super Admin (PRD AD-02). A grant adds a period; it never edits an old one.
 *
 * A renewal starts at max(end of the latest period, now) (PRD section 5). The end is that start
 * plus N calendar months, clamped to the last day of the target month (31 Jan + 1 month = 28 Feb),
 * and is stored as the end of that WIB day, matching how legacy dates were imported.
 */
final class SubscriptionGrantService
{
    public const DURATIONS = [
        '+1 month'  => 1,
        '+3 months' => 3,
        '+6 months' => 6,
        '+1 year'   => 12,
    ];

    public function __construct(private ?SubscriptionRepository $subscriptions = null)
    {
        $this->subscriptions ??= new SubscriptionRepository();
    }

    /** @return array{starts_at: string, ends_at: string} UTC */
    /**
     * Manual grant by staff (PRD AD-02): needs a reason, kept in subscription_grants.note. It is not
     * a payment and is never counted as revenue.
     */
    public function grant(int $userId, int $actorId, string $duration, DateTimeInterface $now, string $reason = ''): array
    {
        $months = self::DURATIONS[$duration] ?? null;
        if ($months === null) {
            throw new DomainException(lang('Accounts.grantDuration'));
        }
        $reason = trim($reason);
        if (mb_strlen($reason) < 3 || mb_strlen($reason) > 255) {
            throw new DomainException(lang('Accounts.grantReason'));
        }
        $planId = $this->subscriptions->planIdByCode('basic');
        if ($planId === null) {
            throw new DomainException(lang('Accounts.noActivePlan'));
        }

        $nowUtc = SubscriptionAccessService::utc($now);

        return $this->subscriptions->appendPeriod(
            $userId,
            $planId,
            'manual',
            static fn (?string $latestEnd): array => self::period($latestEnd, $nowUtc, $months),
            ['actor_id' => $actorId, 'kind' => 'manual', 'duration_months' => $months, 'note' => $reason],
        );
    }

    public function revoke(int $userId, DateTimeInterface $now): void
    {
        $this->subscriptions->revokeActive($userId, SubscriptionAccessService::utc($now));
    }

    /**
     * @return array{0: string, 1: string} [startsAt, endsAt] as UTC strings
     */
    public static function period(?string $latestEndUtc, string $nowUtc, int $months): array
    {
        $utc   = new DateTimeZone('UTC');
        $wib   = new DateTimeZone('Asia/Jakarta');
        $start = new DateTimeImmutable($latestEndUtc !== null && $latestEndUtc > $nowUtc ? $latestEndUtc : $nowUtc, $utc);

        // Count months on WIB calendar days: the anchor is the last day already covered (or today).
        $anchor = $start->setTimezone($wib);
        if ($latestEndUtc !== null && $latestEndUtc > $nowUtc) {
            $anchor = $anchor->modify('-1 second');
        }
        $anchorDay = $anchor->setTime(0, 0);
        $target    = $anchorDay->modify('first day of this month')->modify('+' . $months . ' months');
        $day       = min((int) $anchorDay->format('d'), (int) $target->format('t'));
        $lastDay   = $target->setDate((int) $target->format('Y'), (int) $target->format('m'), $day);

        return [$start->format('Y-m-d H:i:s'), $lastDay->modify('+1 day')->setTimezone($utc)->format('Y-m-d H:i:s')];
    }
}
