<?php

declare(strict_types=1);

use App\Repositories\CardAnalyticsRepository;
use App\Services\Analytics\CardAnalyticsService;
use App\Services\Cards\SubscriptionAccessService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../_support/Fakes/PeriodListRepository.php';

/**
 * @internal
 */
final class CardAnalyticsServiceTest extends TestCase
{
    private const UA = 'Mozilla/5.0 (Linux; Android 14) Chrome/128 Mobile';

    public function testCountsVisitorGetOnProCard(): void
    {
        $metrics = new MemoryAnalyticsRepository();
        $this->assertTrue(self::service($metrics, 'pro')->recordView(self::card(), self::visit(), self::now()));

        $this->assertSame(1, $metrics->days['2026-10-11']['views']);
        $this->assertSame(1, $metrics->days['2026-10-11']['unique_visitors']);
        $this->assertSame(64, strlen($metrics->hashes[0]));
        $this->assertStringNotContainsString('203.0.113.9', implode('', $metrics->hashes));
    }

    public function testExcludedVisitsAreNotCounted(): void
    {
        $metrics  = new MemoryAnalyticsRepository();
        $service  = self::service($metrics, 'pro');
        $excluded = [
            ['method' => 'HEAD'],
            ['user_agent' => 'Googlebot/2.1'],
            ['user_agent' => 'WhatsApp/2.23 A'],
            ['user_agent' => ''],
            ['viewer_id' => 7, 'viewer_role' => 'User'],
            ['viewer_id' => 1, 'viewer_role' => 'Admin'],
            ['viewer_id' => 2, 'viewer_role' => 'Super Admin'],
        ];

        foreach ($excluded as $change) {
            $this->assertFalse($service->recordView(self::card(), $change + self::visit(), self::now()), json_encode($change));
        }
        $this->assertSame([], $metrics->days);

        // Another signed-in owner looking at this card is an ordinary visitor.
        $this->assertTrue($service->recordView(self::card(), ['viewer_id' => 8, 'viewer_role' => 'User'] + self::visit(), self::now()));
    }

    public function testBasicCardIsNotCounted(): void
    {
        $metrics = new MemoryAnalyticsRepository();
        $this->assertFalse(self::service($metrics, 'basic')->recordView(self::card(), self::visit(), self::now()));
        $this->assertSame([], $metrics->days);
    }

    public function testSameVisitorSameDayIsOneUniqueButEveryViewCounts(): void
    {
        $metrics = new MemoryAnalyticsRepository();
        $service = self::service($metrics, 'pro');
        $service->recordView(self::card(), self::visit(), self::now());
        $service->recordView(self::card(), self::visit(), self::now());
        $service->recordView(self::card(), ['ip' => '198.51.100.4'] + self::visit(), self::now());

        $this->assertSame(['views' => 3, 'unique_visitors' => 2, 'contact_clicks' => 0], $metrics->days['2026-10-11']);
    }

    public function testVisitorHashChangesEveryDay(): void
    {
        $metrics = new MemoryAnalyticsRepository();
        $service = self::service($metrics, 'pro');
        $service->recordView(self::card(), self::visit(), self::now());
        $service->recordView(self::card(), self::visit(), new DateTimeImmutable('2026-10-12 10:00', new DateTimeZone('Asia/Jakarta')));

        $this->assertNotSame($metrics->hashes[0], $metrics->hashes[1]);
    }

    public function testWithoutSecretViewsCountButNoUniqueAndNoClicks(): void
    {
        $metrics = new MemoryAnalyticsRepository();
        $service = self::service($metrics, 'pro', '');
        $service->recordView(self::card(), self::visit(), self::now());

        $this->assertSame(['views' => 1, 'unique_visitors' => 0, 'contact_clicks' => 0], $metrics->days['2026-10-11']);
        $this->assertNull($service->eventToken(7, self::now()));
    }

    public function testStorageFailureNeverBreaksTheCard(): void
    {
        $metrics = new class () extends MemoryAnalyticsRepository {
            public function recordView(int $cardUserId, string $dateWib, ?string $visitorHash): void
            {
                throw new RuntimeException('database down');
            }
        };

        $this->assertFalse(self::service($metrics, 'pro')->recordView(self::card(), self::visit(), self::now()));
    }

    public function testWibDayBoundary(): void
    {
        // 17:30 UTC on 10 October is 00:30 WIB on 11 October.
        $this->assertSame('2026-10-11', CardAnalyticsService::wibDay(new DateTimeImmutable('2026-10-10 17:30', new DateTimeZone('UTC'))));
    }

    public function testContactClickNeedsTodaysTokenAndKnownType(): void
    {
        $metrics = new MemoryAnalyticsRepository();
        $service = self::service($metrics, 'pro');
        $token   = $service->eventToken(7, self::now());

        $this->assertFalse($service->recordContactClick(self::card(), 'sms', $token, self::UA, self::now()));
        $this->assertFalse($service->recordContactClick(self::card(), 'whatsapp', 'forged', self::UA, self::now()));
        $this->assertFalse($service->recordContactClick(['id_user' => 8, 'access_level' => 'User'], 'whatsapp', $token, self::UA, self::now()));
        $this->assertFalse($service->recordContactClick(self::card(), 'whatsapp', $token, self::UA, new DateTimeImmutable('2026-10-12 10:00', new DateTimeZone('Asia/Jakarta'))));
        $this->assertSame([], $metrics->days);

        $this->assertTrue($service->recordContactClick(self::card(), 'phone', $token, self::UA, self::now()));
        $this->assertSame(1, $metrics->days['2026-10-11']['contact_clicks']);
    }

    public function testMonthHasEveryDateWithTodayAndFutureMarked(): void
    {
        $metrics       = new MemoryAnalyticsRepository();
        $metrics->days = [
            '2026-10-02' => ['views' => 5, 'unique_visitors' => 3, 'contact_clicks' => 1],
            '2026-10-11' => ['views' => 2, 'unique_visitors' => 2, 'contact_clicks' => 0],
            // A row dated in the future must never show (clock skew, bad import).
            '2026-10-20' => ['views' => 9, 'unique_visitors' => 9, 'contact_clicks' => 9],
        ];
        $stats = self::service($metrics, 'pro')->month(7, '2026-10', self::now());

        $this->assertCount(31, $stats['days']);
        $this->assertSame('Oktober 2026', $stats['label']);
        $this->assertSame(['views' => 7, 'unique_visitors' => 5, 'contact_clicks' => 1], $stats['totals']);
        $this->assertTrue($stats['days'][10]['is_today']);
        $this->assertFalse($stats['days'][10]['is_future']);
        $this->assertTrue($stats['days'][11]['is_future']);
        $this->assertSame(0, $stats['days'][19]['views']);
        $this->assertSame(['2026-10-01', '2026-11-01'], $metrics->lastRange);
    }

    public function testMonthOutsideRetentionFallsBackToCurrent(): void
    {
        $service = self::service(new MemoryAnalyticsRepository(), 'pro');

        $this->assertSame('2026-10', $service->month(7, '2025-01', self::now())['month']);
        $this->assertSame('2026-10', $service->month(7, '2026-13', self::now())['month']);
        $this->assertSame('2026-09', $service->month(7, '2026-09', self::now())['month']);
        $this->assertCount(30, $service->month(7, '2026-09', self::now())['days']);

        $months = CardAnalyticsService::selectableMonths(self::now());
        $this->assertCount(12, $months);
        $this->assertSame(['2026-10', '2025-11'], [$months[0]['value'], $months[11]['value']]);
    }

    public function testPurgeUsesRetentionWindows(): void
    {
        $metrics = new MemoryAnalyticsRepository();
        self::service($metrics, 'pro')->purge(self::now());

        $this->assertSame(['2026-09-11', '2025-11-01'], $metrics->purgedBefore);
    }

    public function testBotDetection(): void
    {
        $this->assertFalse(CardAnalyticsService::isBot(self::UA));
        $this->assertTrue(CardAnalyticsService::isBot('facebookexternalhit/1.1'));
        $this->assertTrue(CardAnalyticsService::isBot('curl/8.5'));
    }

    private static function service(CardAnalyticsRepository $metrics, string $plan, string $secret = 'test-secret'): CardAnalyticsService
    {
        $periods = new class ([7 => [['2026-01-01 00:00:00', '2099-01-01 00:00:00']]], $plan) extends PeriodListRepository {
            public function __construct(array $periods, private string $plan)
            {
                parent::__construct($periods);
            }

            public function currentForUser(int $userId, string $nowUtc): ?array
            {
                $row = parent::currentForUser($userId, $nowUtc);

                return $row === null ? null : ['plan_code' => $this->plan] + $row;
            }
        };

        return new CardAnalyticsService($metrics, new SubscriptionAccessService($periods), $secret);
    }

    private static function card(): array
    {
        return ['id_user' => 7, 'username' => 'u10', 'access_level' => 'User'];
    }

    private static function visit(): array
    {
        return ['method' => 'GET', 'ip' => '203.0.113.9', 'user_agent' => self::UA, 'viewer_id' => null, 'viewer_role' => null];
    }

    private static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-10-11 10:00', new DateTimeZone('Asia/Jakarta'));
    }
}

class MemoryAnalyticsRepository extends CardAnalyticsRepository
{
    public array $days         = [];
    public array $hashes       = [];
    public array $lastRange    = [];
    public array $purgedBefore = [];

    public function __construct()
    {
    }

    public function recordView(int $cardUserId, string $dateWib, ?string $visitorHash): void
    {
        $this->days[$dateWib] ??= ['views' => 0, 'unique_visitors' => 0, 'contact_clicks' => 0];
        $this->days[$dateWib]['views']++;
        if ($visitorHash !== null && ! in_array($visitorHash, $this->hashes, true)) {
            $this->hashes[] = $visitorHash;
            $this->days[$dateWib]['unique_visitors']++;
        }
    }

    public function recordContactClick(int $cardUserId, string $dateWib): void
    {
        $this->days[$dateWib] ??= ['views' => 0, 'unique_visitors' => 0, 'contact_clicks' => 0];
        $this->days[$dateWib]['contact_clicks']++;
    }

    public function month(int $cardUserId, string $firstDay, string $nextMonthFirstDay): array
    {
        $this->lastRange = [$firstDay, $nextMonthFirstDay];

        return $this->days;
    }

    public function purge(string $visitorsBefore, string $metricsBefore): array
    {
        $this->purgedBefore = [$visitorsBefore, $metricsBefore];

        return ['visitors' => 0, 'metrics' => 0];
    }
}
