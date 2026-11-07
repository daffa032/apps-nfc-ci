<?php

declare(strict_types=1);

use App\Services\Cards\SubscriptionAccessService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../_support/Fakes/PeriodListRepository.php';

final class SubscriptionAccessServiceTest extends TestCase
{
    public function testActiveThroughTheEndOfTheCoveredWibDay(): void
    {
        // Legacy 10 Oct imported as ending 11 Oct 00:00 WIB = 10 Oct 17:00 UTC.
        $access = $this->access([1 => [['2026-01-01 00:00:00', '2026-10-10 17:00:00']]]);
        $account = ['id_user' => 1, 'access_level' => 'User'];

        self::assertTrue($access->isActive($account, $this->utc('2026-10-10 16:59:59')));
        self::assertFalse($access->isActive($account, $this->utc('2026-10-10 17:00:00')));
        self::assertFalse($access->isActive($account, $this->utc('2026-10-10 18:00:00')));
    }

    public function testFuturePeriodDoesNotGrantAccessYet(): void
    {
        $access = $this->access([1 => [['2026-11-01 00:00:00', '2026-12-01 00:00:00']]]);

        self::assertFalse($access->isActive(['id_user' => 1, 'access_level' => 'User'], $this->utc('2026-10-10 05:00:00')));
    }

    public function testAccountWithoutPeriodIsInactiveButStaffIsActive(): void
    {
        $access = $this->access([]);
        $now = $this->utc('2026-10-10 05:00:00');

        self::assertFalse($access->isActive(['id_user' => 1, 'access_level' => 'User'], $now));
        self::assertFalse($access->isActive(['access_level' => 'User'], $now));
        self::assertTrue($access->isActive(['id_user' => 2, 'access_level' => 'Admin'], $now));
        self::assertTrue($access->isActive(['id_user' => 3, 'access_level' => 'Super Admin'], $now));
        self::assertFalse($access->isStaff(['access_level' => 'user']));
    }

    public function testPublicCardFollowsTheSameRule(): void
    {
        $access = $this->access([1 => [['2026-01-01 00:00:00', '2026-10-10 17:00:00']]]);
        $now = $this->utc('2026-10-10 05:00:00');

        self::assertTrue($access->isCardPublic(['id_user' => 1, 'access_level' => 'User'], $now));
        self::assertFalse($access->isCardPublic(['id_user' => 2, 'access_level' => 'User'], $now));
    }

    private function access(array $periods): SubscriptionAccessService
    {
        return new SubscriptionAccessService(new PeriodListRepository($periods));
    }

    private function utc(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable($time, new DateTimeZone('UTC'));
    }
}
