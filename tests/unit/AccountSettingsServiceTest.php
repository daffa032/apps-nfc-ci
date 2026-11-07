<?php

declare(strict_types=1);

use App\Repositories\AccountRepository;
use App\Services\Users\AccountSettingsService;
use PHPUnit\Framework\TestCase;

final class AccountSettingsServiceTest extends TestCase
{
    public function testBlankNewPasswordPreservesExistingHash(): void
    {
        $repository = new InMemoryAccountRepository();
        (new AccountSettingsService($repository))->update(1, [
            'email' => 'new@example.test',
            'current_password' => 'current-password',
            'password' => '',
            'repeat_password' => '',
        ]);

        self::assertSame('new@example.test', $repository->updated['email']);
        self::assertArrayNotHasKey('password', $repository->updated);
        self::assertNull($repository->updated['reset_token']);
    }

    public function testCurrentPasswordIsRequiredForChanges(): void
    {
        $repository = new InMemoryAccountRepository();
        $this->expectException(DomainException::class);
        (new AccountSettingsService($repository))->update(1, [
            'email' => 'new@example.test',
            'current_password' => 'wrong',
        ]);
    }

    public function testSubmittingWithoutChangesIsReportedAndWritesNothing(): void
    {
        $repository = new InMemoryAccountRepository();
        foreach (['wrong', 'current-password'] as $currentPassword) {
            try {
                (new AccountSettingsService($repository))->update(1, [
                    'email' => 'old@example.test',
                    'current_password' => $currentPassword,
                    'password' => '',
                    'repeat_password' => '',
                ]);
                self::fail('An unchanged form must not be reported as saved.');
            } catch (DomainException $exception) {
                self::assertSame('Tidak ada perubahan untuk disimpan.', $exception->getMessage());
            }
        }
        self::assertSame([], $repository->updated);
    }
}

class InMemoryAccountRepository extends AccountRepository
{
    public array $updated = [];

    public function __construct()
    {
    }

    public function find(int $id): ?array
    {
        return $id === 1 ? [
            'id_user' => 1,
            'email' => 'old@example.test',
            'password' => password_hash('current-password', PASSWORD_DEFAULT),
        ] : null;
    }

    public function emailTaken(string $email, int $exceptId): bool
    {
        return false;
    }

    public function update(int $id, array $changes): bool
    {
        $this->updated = $changes;
        return true;
    }
}
