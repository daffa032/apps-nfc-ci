<?php

declare(strict_types=1);

use App\Exceptions\FieldException;
use App\Repositories\AccountRepository;
use App\Repositories\BrandAssetRepository;
use App\Repositories\ProfileRepository;
use App\Repositories\TemplateRepository;
use App\Services\Cards\BrandAssetService;
use App\Services\Cards\SubscriptionAccessService;
use App\Services\Cards\TemplateCatalog;
use App\Services\Uploads\PublicImageStorage;
use CodeIgniter\HTTP\Files\UploadedFile;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../_support/Fakes/PeriodListRepository.php';

final class BrandAssetServiceTest extends TestCase
{
    public function testSlotsFollowTheDesign(): void
    {
        self::assertSame(['brand_logo', 'header_background'], TemplateCatalog::brandSlots('layout-pro-aurora'));
        self::assertSame(['brand_logo'], TemplateCatalog::brandSlots('layout-pro-rona'));
        self::assertSame(['brand_logo'], TemplateCatalog::brandSlots('layout-pro-sorot'));
        foreach (['ombak', 'siluet', 'bingkai'] as $design) {
            self::assertSame(['brand_logo', 'header_background'], TemplateCatalog::brandSlots('layout-pro-' . $design), $design);
        }
        self::assertSame([], TemplateCatalog::brandSlots('index-white.php'));
        self::assertSame([], TemplateCatalog::brandSlots('layout-hangat'));
        self::assertSame([], TemplateCatalog::brandSlots('custom-12'));
    }

    public function testLogoFrameFollowsTheDesignContainer(): void
    {
        self::assertSame(['shape' => 'circle', 'ratio' => 1], TemplateCatalog::logoFrame('rona'));
        self::assertSame(['shape' => 'rounded', 'ratio' => 2], TemplateCatalog::logoFrame('meridian'));
        self::assertSame(['shape' => 'square', 'ratio' => 2], TemplateCatalog::logoFrame('ledger'));
        self::assertSame(['shape' => 'rounded', 'ratio' => 2], TemplateCatalog::logoFrame(null));
        foreach (\App\Services\Cards\TemplateHtmlSanitizer::PRO_DESIGNS as $design) {
            self::assertContains(TemplateCatalog::logoFrame($design)['shape'], ['circle', 'rounded', 'square'], $design);
        }
    }

    public function testProOwnerStoresNewFileAndOldOneIsDeletedAfterSave(): void
    {
        [$service, $assets, $storage] = $this->service('layout-pro-aurora', 'pro', ['brand_logo' => 'old.webp']);
        $changed = $service->save(7, ['brand_logo' => $this->file()], [], new DateTimeImmutable());

        self::assertSame(['brand_logo'], $changed);
        self::assertSame(['brand_logo' => 'new1.webp'], $assets->saved);
        self::assertSame(['old.webp'], $storage->deleted);
    }

    public function testRonaRefusesHeaderBackgroundEvenByDirectPost(): void
    {
        [$service, $assets, $storage] = $this->service('layout-pro-rona', 'pro');
        $this->assertField('header_background', fn () => $service->save(7, ['header_background' => $this->file()], [], new DateTimeImmutable()));
        self::assertSame([], $storage->stored);
        self::assertNull($assets->saved);
    }

    public function testBasicCannotUpload(): void
    {
        [$service, $assets, $storage] = $this->service('layout-pro-aurora', 'basic');
        $this->assertField('brand_logo', fn () => $service->save(7, ['brand_logo' => $this->file()], [], new DateTimeImmutable()));
        self::assertSame([], $storage->stored);
    }

    public function testFailedSaveRemovesTheNewFileAndKeepsTheOldOne(): void
    {
        [$service, $assets, $storage] = $this->service('layout-pro-aurora', 'pro', ['brand_logo' => 'old.webp']);
        $assets->fail = true;
        try {
            $service->save(7, ['brand_logo' => $this->file()], [], new DateTimeImmutable());
            self::fail('Must fail');
        } catch (RuntimeException) {
        }
        self::assertSame(['new1.webp'], $storage->deleted, 'Only the new file is removed; the old one stays.');
    }

    public function testCardShowsAssetsOnlyWhileProAndOnlyForSlotsOfTheDesign(): void
    {
        [$service] = $this->service('layout-pro-rona', 'pro', ['brand_logo' => 'logo.webp', 'header_background' => 'bg.webp']);
        self::assertSame(['brand_logo' => 'logo.webp', 'header_background' => null, 'logo_hidden' => false], $service->forCard(['id_user' => 7, 'access_level' => 'User'], ['id_data' => 3], 'layout-pro-rona', new DateTimeImmutable()));

        [$basic] = $this->service('layout-pro-aurora', 'basic', ['brand_logo' => 'logo.webp']);
        self::assertSame(['brand_logo' => null, 'header_background' => null, 'logo_hidden' => false], $basic->forCard(['id_user' => 7, 'access_level' => 'User'], ['id_data' => 3], 'layout-pro-aurora', new DateTimeImmutable()));
    }

    public function testSwitchedOffLogoIsKeptButNotShown(): void
    {
        [$service, $assets, $storage] = $this->service('layout-pro-aurora', 'pro', ['brand_logo' => 'logo.webp', 'brand_logo_visible' => true]);
        $changed = $service->save(7, [], [], new DateTimeImmutable(), ['brand_logo' => false]);

        self::assertSame(['brand_logo'], $changed);
        self::assertSame(['brand_logo_visible' => false], $assets->saved, 'Only the switch is written; the file name stays.');
        self::assertSame([], $storage->deleted);

        [$hidden] = $this->service('layout-pro-aurora', 'pro', ['brand_logo' => 'logo.webp', 'brand_logo_visible' => false]);
        self::assertSame(['brand_logo' => null, 'header_background' => null, 'logo_hidden' => true], $hidden->forCard(['id_user' => 7, 'access_level' => 'User'], ['id_data' => 3], 'layout-pro-aurora', new DateTimeImmutable()));
    }

    public function testUnchangedSwitchIsNotAChange(): void
    {
        [$service, $assets] = $this->service('layout-pro-aurora', 'pro', ['brand_logo' => 'logo.webp', 'brand_logo_visible' => true]);
        self::assertSame([], $service->save(7, [], [], new DateTimeImmutable(), ['brand_logo' => true]));
        self::assertNull($assets->saved);
    }

    public function testBasicCannotSwitchAssets(): void
    {
        [$service] = $this->service('layout-pro-aurora', 'basic', ['brand_logo' => 'logo.webp', 'brand_logo_visible' => true]);
        $this->assertField('brand_logo', fn () => $service->save(7, [], [], new DateTimeImmutable(), ['brand_logo' => false]));
    }

    private function assertField(string $field, callable $call): void
    {
        try {
            $call();
            self::fail('Must be refused');
        } catch (FieldException $exception) {
            self::assertSame($field, $exception->field);
        }
    }

    private function file(): UploadedFile
    {
        // The fake storage never reads it; only "a file was chosen" matters here.
        return new UploadedFile('/tmp/does-not-matter', 'logo.png', 'image/png', 10, UPLOAD_ERR_OK);
    }

    /**
     * @return array{BrandAssetService, object, object}
     */
    private function service(string $template, string $plan, array $stored = []): array
    {
        $profiles = new class ($template) extends ProfileRepository {
            public function __construct(private string $template)
            {
            }

            public function findForUser(int $userId): ?array
            {
                return $userId === 7 ? ['id_data' => 3, 'id_user' => 7, 'templates' => $this->template] : null;
            }
        };
        $assets = new class ($stored) extends BrandAssetRepository {
            public ?array $saved = null;
            public bool $fail = false;

            public function __construct(private array $stored)
            {
            }

            public function find(int $profileId, string $templateFile): ?array
            {
                return $this->stored ?: null;
            }

            public function save(int $profileId, string $templateFile, array $fields, string $nowUtc): void
            {
                if ($this->fail) {
                    throw new RuntimeException('db down');
                }
                $this->saved = $fields;
            }
        };
        $storage = new class extends PublicImageStorage {
            public array $stored = [];
            public array $deleted = [];

            public function store(UploadedFile $file, string $folder): string
            {
                return $this->stored[] = 'new' . (count($this->stored) + 1) . '.webp';
            }

            public function delete(string $folder, string $name): void
            {
                $this->deleted[] = $name;
            }
        };
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
        $access = new SubscriptionAccessService($periods);
        $accounts = new class extends AccountRepository {
            public function __construct()
            {
            }

            public function find(int $id): ?array
            {
                return $id === 7 ? ['id_user' => 7, 'access_level' => 'User'] : null;
            }
        };
        $templates = new class extends TemplateRepository {
            public function __construct()
            {
            }

            public function all(): array
            {
                return [['nama_file' => 'layout-pro-aurora', 'nama_template' => 'Aurora'], ['nama_file' => 'index-white.php', 'nama_template' => 'White']];
            }
        };

        return [new BrandAssetService($profiles, $assets, $storage, $access, $accounts, $templates), $assets, $storage];
    }

    public function testPageShowsStoredAssetsOfTheCurrentDesign(): void
    {
        [$service] = $this->service('layout-pro-aurora', 'pro', ['brand_logo' => 'logo.webp', 'header_background' => 'bg.webp']);
        self::assertSame([
            'slots' => ['brand_logo', 'header_background'],
            'templateName' => 'Aurora',
            'isPro' => true,
            'logoFrame' => ['shape' => 'circle', 'ratio' => 1],
            'brand_logo' => 'logo.webp',
            'header_background' => 'bg.webp',
            'visible' => ['brand_logo' => true, 'header_background' => true],
        ], $service->pageFor(7, new DateTimeImmutable()));
    }
}
