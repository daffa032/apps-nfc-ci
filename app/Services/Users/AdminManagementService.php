<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Exceptions\FieldException;
use App\Repositories\AdminRepository;
use DomainException;

final class AdminManagementService
{
    private const ROLES = ['Admin', 'Super Admin'];

    public function __construct(private ?AdminRepository $admins = null)
    {
        $this->admins ??= new AdminRepository();
    }

    public function list(): array
    {
        return $this->admins->all();
    }

    public function find(int $id): array
    {
        return $this->admins->find($id) ?? throw new DomainException(lang('Accounts.adminMissing'));
    }

    public function create(array $input): void
    {
        $data                      = $this->validatedChanges($input, null, true, null);
        $data['registration_date'] = date('Y-m-d');
        $data['expired_date']      = '9999-12-31';
        $this->admins->create($data);
    }

    public function update(int $id, int $actorId, array $input): void
    {
        $current = $this->find($id);
        if ($id === $actorId) {
            throw new DomainException(lang('Accounts.editOwnInSettings'));
        }
        $this->admins->update($id, $this->validatedChanges($input, $id, false, (string) $current['username']));
    }

    public function delete(int $id, int $actorId): void
    {
        $this->find($id);
        if ($id === $actorId) {
            throw new DomainException(lang('Accounts.cannotDeleteSelf'));
        }
        $this->admins->delete($id);
    }

    private function validatedChanges(array $input, ?int $exceptId, bool $passwordRequired, ?string $currentUsername): array
    {
        $username     = trim((string) ($input['username'] ?? ''));
        $email        = trim((string) ($input['email'] ?? ''));
        $role         = (string) ($input['access_level'] ?? '');
        $password     = (string) ($input['password'] ?? '');
        $confirmation = (string) ($input['repeat_password'] ?? '');

        if ($username === '' || mb_strlen($username) > 255) {
            throw new FieldException('username', lang('Accounts.errUsername'));
        }
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
            throw new FieldException('email', lang('Accounts.errEmail'));
        }
        if ($username !== $currentUsername) {
            UsernamePolicy::assertAllowed($username);
        }
        if (! in_array($role, self::ROLES, true)) {
            throw new FieldException('access_level', lang('Accounts.errRole'));
        }
        if ($this->admins->usernameTaken($username, $exceptId)) {
            throw new FieldException('username', lang('Accounts.usernameTaken'));
        }
        if ($this->admins->emailTaken($email, $exceptId)) {
            throw new FieldException('email', lang('Accounts.emailTaken'));
        }
        if (($passwordRequired && $password === '') || ($password !== '' && strlen($password) < 8)) {
            throw new FieldException('password', lang('Accounts.passwordMin8'));
        }
        if ($password !== $confirmation) {
            throw new FieldException('repeat_password', lang('Accounts.passwordMismatch'));
        }

        $data = ['username' => $username, 'email' => $email, 'access_level' => $role];
        if ($password !== '') {
            $data['password']         = password_hash($password, PASSWORD_DEFAULT);
            $data['reset_token']      = null;
            $data['reset_expired_at'] = null;
        }

        return $data;
    }
}
