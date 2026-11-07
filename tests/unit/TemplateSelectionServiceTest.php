<?php

declare(strict_types=1);

use App\Repositories\AccountRepository;
use App\Repositories\ProfileRepository;
use App\Repositories\TemplateRepository;
use App\Services\Cards\MissingProfileException;
use App\Services\Cards\SubscriptionAccessService;
use App\Services\Cards\TemplateSelectionService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../_support/Fakes/PeriodListRepository.php';

/**
 * @internal
 */
final class TemplateSelectionServiceTest extends TestCase
{
    public function testPageListsActiveCatalogTemplatesWithSelectedFirst(): void
    {
        $page = self::service(new TemplateProfileRepository('index-dark.php'))->pageFor(7);

        $this->assertSame('index-dark.php', $page['selectedTemplate']);
        $this->assertSame([2, 1, 4], array_column($page['templates'], 'id_template'));
        $this->assertNull($page['templates'][1]['images_template']);
    }

    public function testPageIsNullWithoutProfile(): void
    {
        $this->assertNull(self::service(new TemplateProfileRepository(null))->pageFor(7));
    }

    public function testSelectRejectsTemplateOutsideCatalogBeforeWriting(): void
    {
        $profiles = new TemplateProfileRepository('index-white.php');

        foreach ([0, 3, 99] as $templateId) {
            try {
                self::service($profiles)->select(7, $templateId);
                $this->fail('Template ' . $templateId . ' must be rejected.');
            } catch (DomainException $exception) {
                $this->assertNotInstanceOf(MissingProfileException::class, $exception);
            }
        }
        $this->assertSame([], $profiles->saved);
    }

    public function testSelectWithoutProfileAsksForProfile(): void
    {
        $this->expectException(MissingProfileException::class);
        self::service(new TemplateProfileRepository(null))->select(7, 2);
    }

    public function testSelectStoresCatalogFileForAuthenticatedUser(): void
    {
        $profiles = new TemplateProfileRepository('index-white.php');
        self::service($profiles)->select(7, 2);

        $this->assertSame([[7, 'index-dark.php']], $profiles->saved);
    }

    public function testBasicCannotPickProButProCan(): void
    {
        $profiles = new TemplateProfileRepository('index-white.php');

        try {
            self::service($profiles, 'basic')->select(7, 4);
            $this->fail('Basic must not select a Pro template.');
        } catch (DomainException $exception) {
            $this->assertSame('Tampilan ini tersedia untuk paket Pro.', $exception->getMessage());
        }
        $this->assertSame([], $profiles->saved);

        self::service($profiles, 'pro')->select(7, 4);
        $this->assertSame([[7, 'layout-hangat']], $profiles->saved);
    }

    public function testInactiveTemplateCannotBeChosenButStillShowsWhenInUse(): void
    {
        $profiles = new TemplateProfileRepository('layout-gelap');

        try {
            self::service($profiles, 'pro')->select(7, 5);
            $this->fail('Inactive template must be rejected.');
        } catch (DomainException) {
        }

        $page = self::service($profiles, 'basic')->pageFor(7);
        $this->assertSame(['layout-gelap', 'index-white.php', 'index-dark.php', 'layout-hangat'], array_column($page['templates'], 'nama_file'));
        $this->assertSame([true, true, true, false], array_column($page['templates'], 'allowed'));
        $this->assertSame('basic', $page['planCode']);
    }

    private static function service(TemplateProfileRepository $profiles, string $plan = 'basic'): TemplateSelectionService
    {
        $periods    = [7 => [['2026-01-01 00:00:00', '2099-01-01 00:00:00']]];
        $repository = new class ($periods, $plan) extends PeriodListRepository {
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

        return new TemplateSelectionService(new InMemoryTemplateRepository(), $profiles, new TemplateAccounts(), new SubscriptionAccessService($repository));
    }
}

class InMemoryTemplateRepository extends TemplateRepository
{
    private array $rows = [
        1 => ['id_template' => 1, 'nama_template' => 'White', 'nama_file' => 'index-white.php', 'images_template' => '../x.png'],
        2 => ['id_template' => 2, 'nama_template' => 'Black', 'nama_file' => 'index-dark.php', 'images_template' => 'index-black.png'],
        3 => ['id_template' => 3, 'nama_template' => 'Rogue', 'nama_file' => '../../Config/App', 'images_template' => 'x.png'],
        4 => ['id_template' => 4, 'nama_template' => 'Hangat', 'nama_file' => 'layout-hangat', 'images_template' => '', 'plan_code' => 'pro', 'is_active' => 1],
        5 => ['id_template' => 5, 'nama_template' => 'Gelap', 'nama_file' => 'layout-gelap', 'images_template' => '', 'plan_code' => 'pro', 'is_active' => 0],
    ];

    public function __construct()
    {
    }

    public function all(): array
    {
        return array_values($this->rows);
    }

    public function find(int $templateId): ?array
    {
        return $this->rows[$templateId] ?? null;
    }
}

class TemplateProfileRepository extends ProfileRepository
{
    public array $saved = [];

    public function __construct(private ?string $template)
    {
    }

    public function findForUser(int $userId): ?array
    {
        return $this->template === null ? null : ['id_data' => 3, 'id_user' => $userId, 'templates' => $this->template];
    }

    public function saveTemplateForUser(int $userId, string $templateFile): bool
    {
        $this->saved[] = [$userId, $templateFile];

        return true;
    }
}

class TemplateAccounts extends AccountRepository
{
    public function __construct()
    {
    }

    public function find(int $id): ?array
    {
        return ['id_user' => $id, 'access_level' => 'User'];
    }
}
