<?php

declare(strict_types=1);

use App\Services\Cards\SubscriptionGrantService;
use PHPUnit\Framework\TestCase;

final class SubscriptionGrantServiceTest extends TestCase
{
    public function testExpiredOrNewAccountStartsNowAndEndsAtEndOfWibDay(): void
    {
        // Now: 10 Oct 2026 12:00 WIB. +1 month covers through 10 Nov (WIB), so ends 11 Nov 00:00 WIB = 10 Nov 17:00 UTC.
        [$start, $end] = SubscriptionGrantService::period(null, '2026-10-10 05:00:00', 1);
        self::assertSame(['2026-10-10 05:00:00', '2026-11-10 17:00:00'], [$start, $end]);

        // An ended period is ignored: renewal starts now (PRD section 5).
        self::assertSame(['2026-10-10 05:00:00', '2026-11-10 17:00:00'], SubscriptionGrantService::period('2026-09-01 17:00:00', '2026-10-10 05:00:00', 1));
    }

    public function testRenewalOfActivePeriodContinuesFromItsEnd(): void
    {
        // Active through 20 Oct WIB (ends 21 Oct 00:00 WIB = 20 Oct 17:00 UTC). +1 month -> through 20 Nov.
        self::assertSame(['2026-10-20 17:00:00', '2026-11-20 17:00:00'], SubscriptionGrantService::period('2026-10-20 17:00:00', '2026-10-10 05:00:00', 1));
    }

    public function testMonthEndAndLeapYearClamp(): void
    {
        // Covered through 31 Jan 2027 WIB -> +1 month = 28 Feb 2027.
        [, $end] = SubscriptionGrantService::period('2027-01-31 17:00:00', '2027-01-10 05:00:00', 1);
        self::assertSame('2027-02-28 17:00:00', $end);

        // Covered through 29 Feb 2028 WIB -> +12 months = 28 Feb 2029.
        [, $end] = SubscriptionGrantService::period('2028-02-29 17:00:00', '2028-02-10 05:00:00', 12);
        self::assertSame('2029-02-28 17:00:00', $end);
    }

    public function testEarlyMorningWibCountsTheWibDate(): void
    {
        // 11 Oct 2026 01:00 WIB is still 10 Oct in UTC; months count from 11 Oct WIB.
        [, $end] = SubscriptionGrantService::period(null, '2026-10-10 18:00:00', 1);
        self::assertSame('2026-11-11 17:00:00', $end);
    }
}
