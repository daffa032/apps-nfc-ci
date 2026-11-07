<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Exceptions\FieldException;
use App\Repositories\UserRepository;
use App\Services\Cards\SubscriptionGrantService;
use DateTimeImmutable;
use DomainException;

final class UserManagementService
{
    public function __construct(private ?UserRepository $users = null, private ?SubscriptionGrantService $grants = null)
    {
        $this->users ??= new UserRepository();
        $this->grants ??= new SubscriptionGrantService();
    }

    public function list(): array
    {
        return $this->users->allCustomers();
    }

    public function find(int $id): array
    {
        return $this->users->findCustomer($id) ?? throw new DomainException(lang('Accounts.userMissing'));
    }

    public function create(array $input): void
    {
        $username = trim((string) ($input['username'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $password = (string) ($input['password'] ?? '');

        $this->validateIdentity($username, $email);
        UsernamePolicy::assertAllowed($username);
        $this->validatePassword($password, (string) ($input['repeat_password'] ?? ''), true);
        $this->assertUnique($username, $email);

        $this->users->createCustomer([
            'username' => $username,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'registration_date' => date('Y-m-d'),
            'expired_date' => '0000-00-00',
            'access_level' => 'User',
        ]);
    }

    public function update(int $id, array $input): void
    {
        $current = $this->find($id);
        $username = trim((string) ($input['username'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $password = (string) ($input['password'] ?? '');

        $this->validateIdentity($username, $email);
        if ($username !== $current['username']) {
            UsernamePolicy::assertAllowed($username);
        }
        $this->validatePassword($password, (string) ($input['repeat_password'] ?? ''), false);
        $this->assertUnique($username, $email, $id);

        $data = ['username' => $username, 'email' => $email];
        if ($password !== '') {
            $data['password'] = password_hash($password, PASSWORD_DEFAULT);
        }

        if (!$this->users->updateCustomer($id, $data, (string) $current['username'])) {
            throw new DomainException(lang('Accounts.userUpdateFailed'));
        }
    }

    public function delete(int $id): void
    {
        $this->find($id);
        if (!$this->users->deleteCustomer($id)) {
            throw new DomainException(lang('Accounts.userDeleteFailed'));
        }
    }

    /**
     * Super Admin grant (duration, with a reason, PRD AD-02) or revoke (null). Writes subscription periods; the legacy
     * expired_date column is kept in step by SubscriptionRepository.
     */
    public function changeAccess(int $id, ?string $duration, int $actorId = 0, ?DateTimeImmutable $now = null, string $reason = ''): void
    {
        $this->find($id);
        $now ??= new DateTimeImmutable('now');

        if ($duration === null) {
            $this->grants->revoke($id, $now);
            return;
        }
        $this->grants->grant($id, $actorId, $duration, $now, $reason);
    }

    private function validateIdentity(string $username, string $email): void
    {
        if ($username === '' || mb_strlen($username) > 255) {
            throw new FieldException('username', lang('Accounts.errUsername'));
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
            throw new FieldException('email', lang('Accounts.errEmail'));
        }
    }

    private function validatePassword(string $password, string $confirmation, bool $required): void
    {
        if ($required && $password === '') {
            throw new FieldException('password', lang('Accounts.passwordRequired'));
        }
        if ($password !== $confirmation) {
            throw new FieldException('repeat_password', lang('Accounts.passwordMismatch'));
        }
        if ($password !== '' && strlen($password) < 8) {
            throw new FieldException('password', lang('Accounts.passwordMin8'));
        }
    }

    private function assertUnique(string $username, string $email, ?int $exceptId = null): void
    {
        if ($this->users->usernameTaken($username, $exceptId)) {
            throw new FieldException('username', lang('Accounts.usernameTaken'));
        }
        if ($this->users->emailTaken($email, $exceptId)) {
            throw new FieldException('email', lang('Accounts.emailTaken'));
        }
    }
}
