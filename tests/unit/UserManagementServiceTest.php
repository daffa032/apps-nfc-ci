<?php

declare(strict_types=1);

use App\Repositories\SubscriptionRepository;
use App\Repositories\UserRepository;
use App\Services\Cards\SubscriptionGrantService;
use App\Services\Users\UserManagementService;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class UserManagementServiceTest extends TestCase
{
    public function testPostedRoleCannotCreateAnAdmin(): void
    {
        $repository = new InMemoryUserRepository();
        (new UserManagementService($repository))->create([
            'username'        => 'customer',
            'email'           => 'customer@example.test',
            'password'        => 'secretpass',
            'repeat_password' => 'secretpass',
            'access_level'    => 'Super Admin',
        ]);

        $this->assertSame('User', $repository->created['access_level']);
        $this->assertTrue(password_verify('secretpass', $repository->created['password']));
    }

    public function testAnAdminCannotBeEditedThroughCustomerFlow(): void
    {
        $repository               = new InMemoryUserRepository();
        $repository->customers[7] = ['id_user' => 7, 'access_level' => 'Admin'];

        $this->expectException(DomainException::class);
        (new UserManagementService($repository))->update(7, [
            'username' => 'changed',
            'email'    => 'changed@example.test',
        ]);
    }

    public function testNewUsernameMustBeSafeForTheCardUrl(): void
    {
        foreach (['billing', 'ab', 'nama saya', 'a/b', '...', str_repeat('a', 65)] as $username) {
            $repository = new InMemoryUserRepository();

            try {
                (new UserManagementService($repository))->create(['username' => $username, 'email' => 'c@example.test', 'password' => 'secretpass', 'repeat_password' => 'secretpass']);
                $this->fail($username . ' must be rejected.');
            } catch (DomainException) {
            }
            $this->assertSame([], $repository->created ?? []);
        }
    }

    public function testRenamePassesOldUsernameForAliasButUnchangedLegacyNameIsAllowed(): void
    {
        $repository = new InMemoryUserRepository();
        // A legacy username that breaks today's rules keeps working when it is not changed.
        $repository->customers[5] = ['id_user' => 5, 'username' => 'old name', 'access_level' => 'User'];
        $service                  = new UserManagementService($repository);

        $service->update(5, ['username' => 'old name', 'email' => 'c@example.test']);
        $this->assertSame('old name', $repository->updated['username']);

        $service->update(5, ['username' => 'new.name', 'email' => 'c@example.test']);
        $this->assertSame(['new.name', 'old name'], [$repository->updated['username'], $repository->previousUsername]);
    }

    public function testUnlistedExpirationPeriodIsRejected(): void
    {
        $repository               = new InMemoryUserRepository();
        $repository->customers[5] = ['id_user' => 5, 'access_level' => 'User'];

        $this->expectException(DomainException::class);
        (new UserManagementService($repository, new SubscriptionGrantService(new InMemorySubscriptionRepository())))->changeAccess(5, '+100 years', 1);
    }

    public function testGrantAndRevokeGoThroughSubscriptions(): void
    {
        $repository               = new InMemoryUserRepository();
        $repository->customers[5] = ['id_user' => 5, 'access_level' => 'User'];
        $subscriptions            = new InMemorySubscriptionRepository();
        $service                  = new UserManagementService($repository, new SubscriptionGrantService($subscriptions));
        $now                      = new DateTimeImmutable('2026-10-10 05:00:00', new DateTimeZone('UTC'));

        try {
            $service->changeAccess(5, '+1 month', 9, $now, ' ');
            $this->fail('A manual grant without a reason must be rejected (AD-02).');
        } catch (DomainException) {
            $this->assertSame([], $subscriptions->appended);
        }

        $service->changeAccess(5, '+1 month', 9, $now, 'Transfer bank diterima');
        $this->assertSame([5, 'manual', ['actor_id' => 9, 'kind' => 'manual', 'duration_months' => 1, 'note' => 'Transfer bank diterima']], [$subscriptions->appended[0][0], $subscriptions->appended[0][2], $subscriptions->appended[0][3]]);

        $service->changeAccess(5, null, 9, $now);
        $this->assertSame([[5, '2026-10-10 05:00:00']], $subscriptions->revoked);
        $this->assertSame([], $repository->updated);
    }
}

class InMemorySubscriptionRepository extends SubscriptionRepository
{
    public array $appended = [];
    public array $revoked  = [];

    public function __construct()
    {
    }

    public function planIdByCode(string $code): ?int
    {
        return $code === 'basic' ? 1 : null;
    }

    public function appendPeriod(int $userId, int $planId, string $source, callable $startFor, array $grant): array
    {
        $this->appended[]    = [$userId, $planId, $source, $grant];
        [$startsAt, $endsAt] = $startFor(null);

        return ['starts_at' => $startsAt, 'ends_at' => $endsAt];
    }

    public function revokeActive(int $userId, string $nowUtc): void
    {
        $this->revoked[] = [$userId, $nowUtc];
    }
}

class InMemoryUserRepository extends UserRepository
{
    public array $customers          = [];
    public array $created            = [];
    public array $updated            = [];
    public ?string $previousUsername = null;

    public function __construct()
    {
    }

    public function findCustomer(int $id): ?array
    {
        $user = $this->customers[$id] ?? null;

        return $user && $user['access_level'] === 'User' ? $user : null;
    }

    public function usernameTaken(string $username, ?int $exceptId = null): bool
    {
        return false;
    }

    public function emailTaken(string $email, ?int $exceptId = null): bool
    {
        return false;
    }

    public function createCustomer(array $data): int
    {
        $this->created = $data;

        return 1;
    }

    public function updateCustomer(int $id, array $data, ?string $previousUsername = null): bool
    {
        $this->updated          = $data;
        $this->previousUsername = $previousUsername;

        return true;
    }
}
