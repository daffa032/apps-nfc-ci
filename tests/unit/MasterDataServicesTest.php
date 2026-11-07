<?php

declare(strict_types=1);

use App\Repositories\PlanRepository;
use App\Repositories\TemplateRepository;
use App\Services\Cards\PlanAdminService;
use App\Services\Cards\TemplateAdminService;
use PHPUnit\Framework\TestCase;

final class MasterDataServicesTest extends TestCase
{
    public function testTemplateSlotsAreEnforced(): void
    {
        $repository = new SlotTemplateRepository();
        $service = new TemplateAdminService($repository);

        $cases = [
            [3, 'basic', true, 'Slot Basic penuh (maksimal 2 template aktif). Nonaktifkan template Basic lain terlebih dahulu.'],
            [1, 'basic', false, null],
            [2, 'basic', false, 'Minimal satu template Basic harus tetap aktif.'],
        ];
        foreach ($cases as [$id, $plan, $active, $message]) {
            try {
                $service->update($id, $plan, $active, 1);
                self::assertNull($message, 'Expected rejection for ' . $id);
            } catch (DomainException $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }
        self::assertSame([[1, 0]], $repository->written);
    }

    public function testTotalSlotFollowsTheConfiguredLimit(): void
    {
        $repository = new SlotTemplateRepository(fullPro: true);
        // 2 Basic + 13 Pro active with a limit of 15; a 16th active template is refused.
        $this->expectExceptionMessage('Slot penuh (maksimal 15 template aktif). Nonaktifkan template lain terlebih dahulu.');
        (new TemplateAdminService($repository))->update(16, 'pro', true, 16);
    }

    public function testRaisedLimitAllowsMoreActiveTemplates(): void
    {
        $repository = new SlotTemplateRepository(fullPro: true);
        $repository->limits = ['basic' => 4, 'total' => 20];
        (new TemplateAdminService($repository))->update(16, 'pro', true, 16);
        self::assertSame([[16, 1]], $repository->written);
    }

    public function testLimitsAreValidatedAndNeverBelowWhatIsActive(): void
    {
        $repository = new SlotTemplateRepository(fullPro: true);
        $service = new TemplateAdminService($repository);
        $cases = [
            ['0', '20', 'max_active_basic'],
            ['4', 'abc', 'max_active_total'],
            ['4', '100', 'max_active_total'],
            ['21', '20', 'max_active_basic'],
            ['1', '20', 'max_active_basic'],   // 2 Basic are active
            ['4', '14', 'max_active_total'],   // 15 templates are active
        ];
        foreach ($cases as [$basic, $total, $field]) {
            try {
                $service->updateLimits($basic, $total, 1);
                self::fail("Must reject $basic / $total");
            } catch (App\Exceptions\FieldException $exception) {
                self::assertSame($field, $exception->field, "$basic / $total");
            }
        }
        self::assertNull($repository->savedLimits);

        $service->updateLimits('4', '20', 1);
        self::assertSame([4, 20], $repository->savedLimits);
    }

    public function testNinthProDesignFitsWithinFifteen(): void
    {
        $repository = new SlotTemplateRepository(fullPro: true);
        // Deactivate one Pro design, then the inactive one can take its place.
        (new TemplateAdminService($repository))->update(15, 'pro', false, 15);
        (new TemplateAdminService($repository))->update(16, 'pro', true, 16);
        self::assertSame([[15, 0], [16, 1]], $repository->written);
    }

    public function testRenameValidatesAndKeepsNamesUnique(): void
    {
        $repository = new SlotTemplateRepository();
        $service = new TemplateAdminService($repository);
        foreach (['', '   ', str_repeat('a', 33), 'black digital card'] as $name) {
            try {
                $service->rename(1, $name);
                self::fail("Must reject '$name'");
            } catch (App\Exceptions\FieldException $exception) {
                self::assertSame('nama_template', $exception->field, $name);
            }
        }
        self::assertSame([], $repository->renamed);

        // Same name with other casing on the same template is allowed; spaces are collapsed.
        self::assertSame('White Digital Card', $service->rename(1, '  Kartu   Putih  '));
        self::assertSame([[1, 'Kartu Putih']], $repository->renamed);
        $service->rename(1, 'KARTU PUTIH');
        self::assertSame([1, 'KARTU PUTIH'], $repository->renamed[1]);
    }

    public function testRenameOfMissingTemplateIsRefused(): void
    {
        $this->expectException(DomainException::class);
        (new TemplateAdminService(new SlotTemplateRepository()))->rename(999, 'Baru');
    }

    public function testPlanValidation(): void
    {
        $plans = new RecordingPlanRepository();
        $service = new PlanAdminService($plans);

        foreach ([['abc', '12', false], ['199000', '5', false], ['', '', true], ['1234567890', '12', false]] as [$price, $duration, $enabled]) {
            try {
                $service->update('basic', $price, $duration, $enabled, 1);
                self::fail("Must reject $price / $duration");
            } catch (DomainException) {
            }
        }
        self::assertSame([], $plans->saved);

        self::assertSame(2, $service->update('pro', 'Rp 199.000', '12', true, 4));
        self::assertSame([['pro', 199000, 12, true, 4]], $plans->saved);
        self::assertSame(2, $service->update('basic', '', '', false, 4));
    }
}

class SlotTemplateRepository extends TemplateRepository
{
    public array $written = [];
    /** Limits the tests start from (the original 2 Basic / 15 total). */
    public array $limits = ['basic' => 2, 'total' => 15];
    public ?array $savedLimits = null;
    private array $rows;

    public function __construct(bool $fullPro = false)
    {
        $this->rows = [
            ['id_template' => 1, 'nama_template' => 'White Digital Card', 'nama_file' => 'index-white.php', 'plan_code' => 'basic', 'is_active' => 1],
            ['id_template' => 2, 'nama_template' => 'Black Digital Card', 'nama_file' => 'index-dark.php', 'plan_code' => 'basic', 'is_active' => 1],
            ['id_template' => 3, 'nama_template' => 'Hangat', 'nama_file' => 'layout-hangat', 'plan_code' => 'pro', 'is_active' => 0],
        ];
        if ($fullPro) {
            $files = ['layout-profesional', 'layout-hangat', 'layout-portofolio', 'layout-gelap', 'layout-pro-aurora', 'layout-pro-meridian', 'layout-pro-atelier', 'layout-pro-vista', 'layout-pro-ledger', 'layout-pro-kanvas', 'layout-pro-halo', 'layout-pro-rona', 'layout-pro-sorot'];
            $this->rows = [
                ['id_template' => 1, 'nama_file' => 'index-white.php', 'plan_code' => 'basic', 'is_active' => 1],
                ['id_template' => 2, 'nama_file' => 'index-dark.php', 'plan_code' => 'basic', 'is_active' => 1],
            ];
            foreach ($files as $i => $file) {
                $this->rows[] = ['id_template' => $i + 3, 'nama_file' => $file, 'plan_code' => 'pro', 'is_active' => 1];
            }
            // An uploaded template that is not active yet.
            $this->rows[] = ['id_template' => 16, 'nama_file' => 'custom-16', 'plan_code' => 'pro', 'is_active' => 0];
        }
    }

    public function slotLimits(): array
    {
        return $this->limits;
    }

    public function saveSlotLimits(int $basic, int $total, int $actorId, callable $check): void
    {
        $check($this->rows);
        $this->savedLimits = [$basic, $total];
        $this->limits = ['basic' => $basic, 'total' => $total];
    }

    public array $renamed = [];

    public function renameLocked(int $templateId, string $name, callable $check): string
    {
        foreach ($this->rows as $index => $row) {
            if ((int) $row['id_template'] === $templateId) {
                $check($this->rows);
                $this->renamed[] = [$templateId, $name];
                $previous = (string) $row['nama_template'];
                $this->rows[$index]['nama_template'] = $name;

                return $previous;
            }
        }
        throw new DomainException('Template tidak ditemukan.');
    }

    public function updateLocked(int $templateId, array $changes, callable $check): void
    {
        $rows = array_map(static fn ($row) => (int) $row['id_template'] === $templateId ? $changes + $row : $row, $this->rows);
        $check($rows);
        $this->written[] = [$templateId, $changes['is_active']];
        $this->rows = $rows;
    }
}

class RecordingPlanRepository extends PlanRepository
{
    public array $saved = [];

    public function __construct()
    {
    }

    public function saveVersion(string $code, ?int $priceIdr, ?int $durationMonths, bool $enabled, int $actorId): int
    {
        $this->saved[] = [$code, $priceIdr, $durationMonths, $enabled, $actorId];

        return 2;
    }
}
