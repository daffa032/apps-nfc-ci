<?php

declare(strict_types=1);

use App\Repositories\AccountStatusRepository;
use App\Services\Users\AccountStatus;
use App\Services\Users\AccountStatusService;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class AccountStatusTest extends TestCase
{
    public function testRowsWithoutTheNewColumnsStayUsable(): void
    {
        $this->assertFalse(AccountStatus::isSuspended(['id_user' => 1]));
        $this->assertSame(1, AccountStatus::sessionVersion(['id_user' => 1]));
        $this->assertTrue(AccountStatus::sessionIsCurrent(['id_user' => 1], 1));
    }

    public function testSessionEndsWhenVersionChangesOrAccountIsSuspended(): void
    {
        $this->assertTrue(AccountStatus::sessionIsCurrent(['account_status' => 'enabled', 'session_version' => 3], 3));
        $this->assertTrue(AccountStatus::sessionIsCurrent(['account_status' => 'enabled', 'session_version' => 3], '3'));
        $this->assertFalse(AccountStatus::sessionIsCurrent(['account_status' => 'enabled', 'session_version' => 4], 3));
        $this->assertFalse(AccountStatus::sessionIsCurrent(['account_status' => 'suspended', 'session_version' => 3], 3));
        $this->assertFalse(AccountStatus::sessionIsCurrent(['account_status' => 'enabled', 'session_version' => 1], null));
    }

    public function testOnlySuperAdminMayChangeStatus(): void
    {
        $repository = new InMemoryAccountStatusRepository();

        foreach (['Admin', 'User', ''] as $role) {
            try {
                (new AccountStatusService($repository))->suspend(1, $role, 2);
                $this->fail($role . ' must not suspend accounts.');
            } catch (DomainException $exception) {
                $this->assertSame('Akses ditolak.', $exception->getMessage());
            }
        }
        $this->assertSame([], $repository->writes);
    }

    public function testCannotChangeOwnStatusOrMissingAccount(): void
    {
        $repository = new InMemoryAccountStatusRepository();
        $service    = new AccountStatusService($repository);

        foreach ([[1, 'Anda tidak dapat mengubah status akun sendiri.'], [99, 'Akun tidak ditemukan.'], [0, 'Akun tidak ditemukan.']] as [$target, $message]) {
            try {
                $service->suspend(1, 'Super Admin', $target);
                $this->fail('Target ' . $target . ' must be rejected.');
            } catch (DomainException $exception) {
                $this->assertSame($message, $exception->getMessage());
            }
        }
        $this->assertSame([], $repository->writes);
    }

    public function testSuspendAndRestoreWriteOnlyRealChanges(): void
    {
        $repository = new InMemoryAccountStatusRepository();
        $service    = new AccountStatusService($repository);

        $service->suspend(1, 'Super Admin', 2);
        $this->assertSame([[2, 'suspended']], $repository->writes);

        try {
            $service->suspend(1, 'Super Admin', 3);
            $this->fail('Already suspended account must be reported.');
        } catch (DomainException $exception) {
            $this->assertSame('Akun sudah ditangguhkan.', $exception->getMessage());
        }

        $service->restore(1, 'Super Admin', 3);
        $this->assertSame([[2, 'suspended'], [3, 'enabled']], $repository->writes);
    }
}

class InMemoryAccountStatusRepository extends AccountStatusRepository
{
    public array $writes = [];
    private array $rows  = [
        1 => ['id_user' => 1, 'access_level' => 'Super Admin', 'account_status' => 'enabled'],
        2 => ['id_user' => 2, 'access_level' => 'User', 'account_status' => 'enabled'],
        3 => ['id_user' => 3, 'access_level' => 'Admin', 'account_status' => 'suspended'],
    ];

    public function __construct()
    {
    }

    public function find(int $id): ?array
    {
        return $this->rows[$id] ?? null;
    }

    public function setStatus(int $id, string $status): void
    {
        $this->writes[] = [$id, $status];
    }
}
