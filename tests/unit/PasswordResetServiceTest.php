<?php

declare(strict_types=1);

use App\Repositories\PasswordResetRepository;
use App\Services\Auth\PasswordResetService;
use PHPUnit\Framework\TestCase;

final class PasswordResetServiceTest extends TestCase
{
    public function testTokenIsHashedAndCanOnlyBeUsedOnce(): void
    {
        $repository = new InMemoryPasswordResetRepository();
        $service = new PasswordResetService($repository);
        $token = $service->issue('user@example.test');

        self::assertNotNull($token);
        self::assertSame(64, strlen($token));
        self::assertSame(hash('sha256', $token), $repository->hash);
        self::assertNotSame($token, $repository->hash);
        self::assertTrue($service->isValid($token));

        $service->reset($token, 'new-password-123', 'new-password-123');
        self::assertTrue(password_verify('new-password-123', $repository->passwordHash));
        self::assertFalse($service->isValid($token));

        $this->expectException(DomainException::class);
        $service->reset($token, 'another-password-123', 'another-password-123');
    }

    public function testExpiredTokenAndMismatchedConfirmationAreRejected(): void
    {
        $repository = new InMemoryPasswordResetRepository();
        $service = new PasswordResetService($repository);
        $token = $service->issue('user@example.test');
        $repository->expiresAt = '2000-01-01 00:00:00';
        self::assertFalse($service->isValid($token));

        try {
            $service->reset($token, 'new-password-123', 'wrong-password');
            self::fail('Mismatched confirmation must fail.');
        } catch (DomainException) {
            self::assertNull($repository->passwordHash);
        }

        $this->expectException(DomainException::class);
        $service->reset($token, 'new-password-123', 'new-password-123');
    }
}

class InMemoryPasswordResetRepository extends PasswordResetRepository
{
    public ?string $hash = null;
    public ?string $expiresAt = null;
    public ?string $passwordHash = null;

    public function __construct()
    {
    }

    public function findByEmail(string $email): ?array
    {
        return $email === 'user@example.test' ? ['id_user' => 1] : null;
    }

    public function saveToken(int $userId, string $hash, string $expiresAt): bool
    {
        $this->hash = $hash;
        $this->expiresAt = $expiresAt;
        return true;
    }

    public function hasValidToken(string $hash, string $now): bool
    {
        return $this->hash === $hash && $this->expiresAt >= $now;
    }

    public function consume(string $hash, string $now, string $passwordHash): bool
    {
        if (!$this->hasValidToken($hash, $now)) {
            return false;
        }
        $this->passwordHash = $passwordHash;
        $this->hash = null;
        $this->expiresAt = null;
        return true;
    }

    public function invalidate(string $hash): void
    {
        if ($this->hash === $hash) {
            $this->hash = null;
            $this->expiresAt = null;
        }
    }
}
