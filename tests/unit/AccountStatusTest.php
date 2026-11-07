<?php

declare(strict_types=1);

use App\Repositories\AccountStatusRepository;
use App\Services\Users\AccountStatus;
use App\Services\Users\AccountStatusService;
use PHPUnit\Framework\TestCase;

final class AccountStatusTest extends TestCase
{
    public function testRowsWithoutTheNewColumnsStayUsable(): void
    {
        self::assertFalse(AccountStatus::isSuspended(['id_user' => 1]));
        self::assertSame(1, AccountStatus::sessionVersion(['id_user' => 1]));
        self::assertTrue(AccountStatus::sessionIsCurrent(['id_user' => 1], 1));
    }

    public function testSessionEndsWhenVersionChangesOrAccountIsSuspended(): void
    {
        self::assertTrue(AccountStatus::sessionIsCurrent(['account_status' => 'enabled', 'session_version' => 3], 3));
        self::assertTrue(AccountStatus::sessionIsCurrent(['account_status' => 'enabled', 'session_version' => 3], '3'));
        self::assertFalse(AccountStatus::sessionIsCurrent(['account_status' => 'enabled', 'session_version' => 4], 3));
        self::assertFalse(AccountStatus::sessionIsCurrent(['account_status' => 'suspended', 'session_version' => 3], 3));
        self::assertFalse(AccountStatus::sessionIsCurrent(['account_status' => 'enabled', 'session_version' => 1], null));
    }

    public function testOnlySuperAdminMayChangeStatus(): void
    {
        $repository = new InMemoryAccountStatusRepository();
        foreach (['Admin', 'User', ''] as $role) {
            try {
                (new AccountStatusService($repository))->suspend(1, $role, 2);
                self::fail($role . ' must not suspend accounts.');
            } catch (DomainException $exception) {
                self::assertSame('Akses ditolak.', $exception->getMessage());
            }
        }
        self::assertSame([], $repository->writes);
    }

    public function testCannotChangeOwnStatusOrMissingAccount(): void
    {
        $repository = new InMemoryAccountStatusRepository();
        $service = new AccountStatusService($repository);
        foreach ([[1, 'Anda tidak dapat mengubah status akun sendiri.'], [99, 'Akun tidak ditemukan.'], [0, 'Akun tidak ditemukan.']] as [$target, $message]) {
            try {
                $service->suspend(1, 'Super Admin', $target);
                self::fail('Target ' . $target . ' must be rejected.');
            } catch (DomainException $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }
        self::assertSame([], $repository->writes);
    }

    public function testSuspendAndRestoreWriteOnlyRealChanges(): void
    {
        $repository = new InMemoryAccountStatusRepository();
        $service = new AccountStatusService($repository);

        $service->suspend(1, 'Super Admin', 2);
        self::assertSame([[2, 'suspended']], $repository->writes);

        try {
            $service->suspend(1, 'Super Admin', 3);
            self::fail('Already suspended account must be reported.');
        } catch (DomainException $exception) {
            self::assertSame('Akun sudah ditangguhkan.', $exception->getMessage());
        }

        $service->restore(1, 'Super Admin', 3);
        self::assertSame([[2, 'suspended'], [3, 'enabled']], $repository->writes);
    }
}

class InMemoryAccountStatusRepository extends AccountStatusRepository
{
    public array $writes = [];

    private array $rows = [
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
