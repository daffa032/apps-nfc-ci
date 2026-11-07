<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Repositories\PasswordResetRepository;
use DateTimeImmutable;
use DomainException;
use RuntimeException;

final class PasswordResetService
{
    public function __construct(private ?PasswordResetRepository $resets = null)
    {
        $this->resets ??= new PasswordResetRepository();
    }

    /** Returns a token only when the email belongs to an account. */
    public function issue(string $email): ?string
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $user = $this->resets->findByEmail($email);
        if (!$user) {
            return null;
        }

        $token = bin2hex(random_bytes(32));
        $expiresAt = (new DateTimeImmutable('now'))->modify('+30 minutes')->format('Y-m-d H:i:s');
        if (!$this->resets->saveToken((int) $user['id_user'], hash('sha256', $token), $expiresAt)) {
            throw new RuntimeException(lang('Auth.errIssueFailed'));
        }

        return $token;
    }

    public function isValid(string $token): bool
    {
        return $this->validFormat($token)
            && $this->resets->hasValidToken(hash('sha256', $token), date('Y-m-d H:i:s'));
    }

    public function reset(string $token, string $password, string $confirmation): void
    {
        if (!$this->validFormat($token)) {
            throw new DomainException(lang('Auth.errLinkInvalid'));
        }
        if (strlen($password) < 8 || $password !== $confirmation) {
            throw new DomainException(lang('Auth.errPasswordRule'));
        }

        $hash = hash('sha256', $token);
        if (!$this->resets->consume($hash, date('Y-m-d H:i:s'), password_hash($password, PASSWORD_DEFAULT))) {
            throw new DomainException(lang('Auth.errLinkInvalid'));
        }
    }

    public function invalidate(string $token): void
    {
        if ($this->validFormat($token)) {
            $this->resets->invalidate(hash('sha256', $token));
        }
    }

    private function validFormat(string $token): bool
    {
        return preg_match('/\A[a-f0-9]{64}\z/D', $token) === 1;
    }
}
