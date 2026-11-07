<?php

declare(strict_types=1);

use App\Repositories\AccountRepository;
use App\Repositories\ProfileRepository;
use App\Services\Cards\DashboardService;
use App\Services\Cards\SubscriptionAccessService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../_support/Fakes/PeriodListRepository.php';

final class DashboardServiceTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        helper('date');
    }

    public function testActiveUserWithProfile(): void
    {
        $summary = $this->summary(['access_level' => 'User', 'expired_date' => '2026-10-20'], ['nama' => 'Ada Lovelace', 'company_name' => 'Lab', 'profile_image' => 'a.webp']);

        self::assertTrue($summary['isActive']);
        self::assertFalse($summary['isStaff']);
        self::assertSame('Aktif sampai 20 Oktober 2026', $summary['expiryLabel']);
        self::assertSame('10 hari', $summary['remainingDays']);
        self::assertSame('Ada', $summary['firstName']);
        self::assertSame('AL', $summary['initials']);
        self::assertSame('a.webp', $summary['profileImage']);
        self::assertStringEndsWith('/ada', $summary['publicUrl']);
    }

    public function testLastDayIsActiveAndNextDayIsExpired(): void
    {
        self::assertTrue($this->summary(['access_level' => 'User', 'expired_date' => '2026-10-10'], null)['isActive']);

        $expired = $this->summary(['access_level' => 'User', 'expired_date' => '2026-10-09'], null);
        self::assertFalse($expired['isActive']);
        self::assertFalse($expired['neverActivated']);
        self::assertSame('Berakhir pada 9 Oktober 2026', $expired['expiryLabel']);
    }

    public function testInactiveAccountAndMissingProfile(): void
    {
        $summary = $this->summary(['access_level' => 'User', 'expired_date' => '0000-00-00'], null);

        self::assertFalse($summary['isActive']);
        self::assertTrue($summary['neverActivated']);
        self::assertFalse($summary['hasProfile']);
        self::assertSame('Belum diaktifkan', $summary['expiryLabel']);
        self::assertSame('ada', $summary['displayName']);
        self::assertNull($summary['profileImage']);
    }

    public function testStaffIsActiveRegardlessOfExpiry(): void
    {
        $summary = $this->summary(['access_level' => 'Super Admin', 'expired_date' => '2020-01-01'], null);

        self::assertTrue($summary['isStaff']);
        self::assertTrue($summary['isActive']);
    }

    public function testInitialsIgnoreMarkupAndSymbols(): void
    {
        $initials = fn (string $name): string => $this->summary(['access_level' => 'User', 'expired_date' => '2026-10-20'], ['nama' => $name])['initials'];

        self::assertSame('AS', $initials('Alice <b>Smith</b>'));
        self::assertSame('ÉZ', $initials('élodie zola'));
        self::assertSame('B', $initials('  ** Budi  '));
        self::assertSame('?', $initials('!!!'));
    }

    public function testUnsafeImageNameIsDropped(): void
    {
        self::assertNull($this->summary(['access_level' => 'User', 'expired_date' => '2026-10-20'], ['nama' => 'Ada', 'profile_image' => '../x.php'])['profileImage']);
    }

    public function testMissingAccountReturnsNull(): void
    {
        $service = new DashboardService(new DashboardAccountRepository(null), new DashboardProfileRepository(null), new SubscriptionAccessService(new PeriodListRepository([])));
        self::assertNull($service->summaryFor(7, $this->now()));
        self::assertNull($service->summaryFor(0, $this->now()));
    }

    /**
     * $account['expired_date'] (a WIB day) is turned into one subscription period ending at the end
     * of that day, the same way the migration imports legacy dates. 0000-00-00 means no period.
     */
    private function summary(array $account, ?array $profile): array
    {
        $periods = [];
        $date = $account['expired_date'] ?? '';
        if (preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $date) && $date !== '0000-00-00') {
            $end = (new DateTimeImmutable($date, new DateTimeZone('Asia/Jakarta')))->modify('+1 day')->setTimezone(new DateTimeZone('UTC'));
            $periods[7] = [['2026-01-01 00:00:00', $end->format('Y-m-d H:i:s')]];
        }
        $service = new DashboardService(
            new DashboardAccountRepository(['id_user' => 7, 'username' => 'ada'] + $account),
            new DashboardProfileRepository($profile),
            new SubscriptionAccessService(new PeriodListRepository($periods))
        );

        return $service->summaryFor(7, $this->now());
    }

    private function now(): DateTimeImmutable
    {
        // 10 October 2026, 12:00 WIB
        return new DateTimeImmutable('2026-10-10 05:00:00', new DateTimeZone('UTC'));
    }
}

class DashboardAccountRepository extends AccountRepository
{
    public function __construct(private ?array $account)
    {
    }

    public function find(int $id): ?array
    {
        return $this->account;
    }
}

class DashboardProfileRepository extends ProfileRepository
{
    public function __construct(private ?array $profile)
    {
    }

    public function findForUser(int $userId): ?array
    {
        return $this->profile;
    }
}
