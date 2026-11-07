<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Exceptions\FieldException;
use App\Repositories\AccountRepository;
use DomainException;
use RuntimeException;

final class AccountSettingsService
{
    public function __construct(private ?AccountRepository $accounts = null)
    {
        $this->accounts ??= new AccountRepository();
    }

    public function find(int $id): ?array
    {
        return $this->accounts->find($id);
    }

    public function update(int $id, array $input): void
    {
        $user = $this->accounts->find($id);
        if (!$user) {
            throw new DomainException(lang('Settings.accountMissing'));
        }

        $email = trim((string) ($input['email'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $confirmation = (string) ($input['repeat_password'] ?? '');
        $emailChanged = $email !== $user['email'];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new FieldException('email', lang('Settings.errEmail'));
        }
        if ($emailChanged && $this->accounts->emailTaken($email, $id)) {
            throw new FieldException('email', lang('Settings.errEmailTaken'));
        }
        if (!$emailChanged && $password === '' && $confirmation === '') {
            // Nothing would be written; reporting success here would be a false "saved" (PRD FB-01).
            throw new DomainException(lang('Settings.noChanges'));
        }
        if (!password_verify((string) ($input['current_password'] ?? ''), $user['password'])) {
            throw new FieldException('current_password', lang('Settings.errCurrent'));
        }
        if ($password === '' && $confirmation !== '') {
            throw new FieldException('password', lang('Settings.errNewMissing'));
        }

        $changes = [];
        if ($emailChanged) {
            $changes['email'] = $email;
        }
        if ($password !== '') {
            if (strlen($password) < 8) {
                throw new FieldException('password', lang('Settings.errPasswordMin'));
            }
            if ($password !== $confirmation) {
                throw new FieldException('repeat_password', lang('Settings.errMismatch'));
            }
            $changes['password'] = password_hash($password, PASSWORD_DEFAULT);
        }
        $changes['reset_token'] = null;
        $changes['reset_expired_at'] = null;

        if (!$this->accounts->update($id, $changes)) {
            throw new RuntimeException(lang('Settings.saveFailed'));
        }
    }
}
