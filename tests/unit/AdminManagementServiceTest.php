<?php

declare(strict_types=1);

use App\Repositories\AdminRepository;
use App\Services\Users\AdminManagementService;
use PHPUnit\Framework\TestCase;

final class AdminManagementServiceTest extends TestCase
{
    public function testCreateRejectsRoleOutsideAdminList(): void
    {
        $repository = new InMemoryAdminRepository();
        $this->expectException(DomainException::class);
        try {
            (new AdminManagementService($repository))->create([
                'username' => 'newadmin',
                'email' => 'new@example.test',
                'access_level' => 'User',
                'password' => 'strong-password',
                'repeat_password' => 'strong-password',
            ]);
        } finally {
            self::assertSame([], $repository->created);
        }
    }

    public function testUpdateCannotModifyOwnAdminRecord(): void
    {
        $repository = new InMemoryAdminRepository();
        $this->expectException(DomainException::class);
        (new AdminManagementService($repository))->update(1, 1, [
            'username' => 'changed',
            'email' => 'changed@example.test',
            'access_level' => 'Admin',
        ]);
    }

    public function testBlankPasswordIsNotWrittenOnUpdate(): void
    {
        $repository = new InMemoryAdminRepository();
        (new AdminManagementService($repository))->update(1, 2, [
            'username' => 'admin',
            'email' => 'admin@example.test',
            'access_level' => 'Super Admin',
            'password' => '',
            'repeat_password' => '',
        ]);

        self::assertArrayNotHasKey('password', $repository->updated);
        self::assertSame('Super Admin', $repository->updated['access_level']);
    }

    public function testRenameToReservedRouteIsRejected(): void
    {
        $repository = new InMemoryAdminRepository();
        try {
            (new AdminManagementService($repository))->update(1, 2, ['username' => 'Dashboard', 'email' => 'admin@example.test', 'access_level' => 'Super Admin']);
            self::fail('Reserved username must be rejected.');
        } catch (DomainException $exception) {
            self::assertSame('Username ini dipakai sistem. Pilih username lain.', $exception->getMessage());
        }
        self::assertSame([], $repository->updated);
    }
}

class InMemoryAdminRepository extends AdminRepository
{
    public array $created = [];
    public array $updated = [];

    public function __construct()
    {
    }

    public function find(int $id): ?array
    {
        return $id === 1 ? ['id_user' => 1, 'username' => 'admin', 'access_level' => 'Super Admin'] : null;
    }

    public function usernameTaken(string $username, ?int $exceptId = null): bool
    {
        return false;
    }

    public function emailTaken(string $email, ?int $exceptId = null): bool
    {
        return false;
    }

    public function create(array $data): int
    {
        $this->created = $data;
        return 3;
    }

    public function update(int $id, array $changes): void
    {
        $this->updated = $changes;
    }
}
