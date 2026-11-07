<?php

declare(strict_types=1);

use App\Repositories\AuditLogRepository;
use App\Services\Audit\AuditLogService;
use CodeIgniter\Database\MySQLi\Connection;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class AuditLogServiceTest extends TestCase
{
    public function testRecordsOnlyKnownActionsWithFieldNames(): void
    {
        $repository = new MemoryAuditRepository();
        $service    = new AuditLogService($repository);

        $service->record('user.updated', 'success', 3, 'user', 9, ['email', 'password', 'email']);
        $service->record('made.up', 'success', 3);
        $service->record('user.updated', 'maybe', 3);

        $this->assertCount(1, $repository->events);
        $event = $repository->events[0];
        $this->assertSame('email,password', $event['changed_fields']);
        $this->assertSame('9', $event['target_id']);
        $this->assertMatchesRegularExpression('/\A[a-f0-9]{16}\z/', $event['request_id']);
        $this->assertSame(AuditLogService::requestId(), $event['request_id']);
    }

    public function testFailedWriteDoesNotThrow(): void
    {
        (new AuditLogService(new FailingAuditRepository()))->record('auth.login', 'success', 1);
        $this->addToAssertionCount(1);
    }

    public function testEveryAuditActionUsedInTheCodeIsRegisteredAndTranslated(): void
    {
        // An unregistered action is dropped by record() and makes withRequiredAudit() fail the whole
        // mutation (QA 26 Oct 2026: template.limits_changed broke "Simpan batas").
        $used  = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(APPPATH, FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php' || str_contains($file->getPathname(), DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR)) {
                continue;
            }
            preg_match_all("/(?:audit|auditedMutation|record|appendRequired)\\(\\s*'([a-z_]+\\.[a-z_]+)'/", (string) file_get_contents($file->getPathname()), $m);
            preg_match_all("/\\['([a-z_]+\\.[a-z_]+)', '[a-z_]+', /", (string) file_get_contents($file->getPathname()), $n);
            $used = [...$used, ...$m[1], ...$n[1]];
        }
        $used = array_values(array_unique($used));
        $this->assertNotEmpty($used);
        $id = require APPPATH . 'Language/id/Audit.php';
        $en = require APPPATH . 'Language/en/Audit.php';

        foreach ($used as $action) {
            $this->assertArrayHasKey($action, AuditLogService::ACTIONS, "Audit action {$action} is used but not registered.");
            $key = 'action_' . str_replace('.', '_', $action);
            $this->assertArrayHasKey($key, $id, "Missing id translation for {$action}.");
            $this->assertArrayHasKey($key, $en, "Missing en translation for {$action}.");
        }
    }

    public function testRequiredAuditCommitsMutationWithItsEvent(): void
    {
        $repository = new MemoryAuditRepository();
        $db         = new RecordingTransactions();
        $result     = (new AuditLogService($repository, $db))->withRequiredAudit(
            9,
            static fn (): int => 4,
            static fn (int $version): array => ['plan.updated', 'plan', 'pro', ['price_idr'], 'v' . $version],
        );

        $this->assertSame(4, $result);
        $this->assertSame(['begin', 'commit'], $db->calls);
        $this->assertSame(['plan.updated', 'success', 'v4', 'price_idr'], [$repository->events[0]['action'], $repository->events[0]['outcome'], $repository->events[0]['reason'], $repository->events[0]['changed_fields']]);
    }

    public function testFailedAuditWriteCancelsHighRiskMutation(): void
    {
        $db  = new RecordingTransactions();
        $ran = false;

        try {
            (new AuditLogService(new FailingAuditRepository(), $db))->withRequiredAudit(9, static function () use (&$ran): void {
                $ran = true;
            }, static fn (): array => ['user.deleted', 'user', 5]);
            $this->fail('A failed audit write must surface as a failure.');
        } catch (RuntimeException) {
        }

        $this->assertTrue($ran);
        $this->assertSame(['begin', 'rollback'], $db->calls);
    }

    public function testFailedMutationWritesNoSuccessEvent(): void
    {
        $repository = new MemoryAuditRepository();
        $db         = new RecordingTransactions();

        try {
            (new AuditLogService($repository, $db))->withRequiredAudit(9, static function (): void {
                throw new DomainException('Super Admin terakhir tidak dapat dihapus.');
            }, static fn (): array => ['admin.deleted', 'admin', 1]);
        } catch (DomainException) {
        }

        $this->assertSame([], $repository->events);
        $this->assertSame(['begin', 'rollback'], $db->calls);
    }

    public function testSearchIgnoresUnknownFiltersAndConvertsWibDays(): void
    {
        $repository = new MemoryAuditRepository();
        $result     = (new AuditLogService($repository))->search([
            'action' => 'DROP TABLE', 'outcome' => 'denied', 'target' => "1' OR '1",
            'from'   => '2026-10-10', 'to' => '2026-10-10', 'actor' => 'ada',
        ], 0);

        $this->assertSame(['action' => '', 'outcome' => 'denied', 'actor' => 'ada', 'target' => '', 'from' => '2026-10-09 17:00:00', 'to' => '2026-10-10 17:00:00'], $repository->lastFilters);
        $this->assertSame(1, $result['page']);
        $this->assertSame('2026-10-10', $result['filters']['from']);
    }
}

class MemoryAuditRepository extends AuditLogRepository
{
    public array $events      = [];
    public array $lastFilters = [];

    public function __construct()
    {
    }

    public function append(array $event): void
    {
        $this->events[] = $event;
    }

    public function search(array $filters, int $page): array
    {
        $this->lastFilters = $filters;

        return ['rows' => [], 'total' => 0];
    }
}

class FailingAuditRepository extends AuditLogRepository
{
    public function __construct()
    {
    }

    public function append(array $event): void
    {
        throw new RuntimeException('database down');
    }
}

/**
 * Stands in for the DB connection: only records transaction calls.
 */
class RecordingTransactions extends Connection
{
    public array $calls = [];

    public function __construct()
    {
    }

    public function transBegin(bool $testMode = false): bool
    {
        $this->calls[] = 'begin';

        return true;
    }

    public function transCommit(): bool
    {
        $this->calls[] = 'commit';

        return true;
    }

    public function transRollback(): bool
    {
        $this->calls[] = 'rollback';

        return true;
    }

    public function transStatus(): bool
    {
        return true;
    }
}
