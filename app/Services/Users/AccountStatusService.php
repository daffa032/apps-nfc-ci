<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Repositories\AccountStatusRepository;
use DomainException;

/**
 * Super Admin action to suspend or restore an account (PRD section 5: account status is separate
 * from card expiry). Suspension ends the account's sessions immediately via session_version.
 */
final class AccountStatusService
{
    public function __construct(private ?AccountStatusRepository $accounts = null)
    {
        $this->accounts ??= new AccountStatusRepository();
    }

    public function suspend(int $actorId, string $actorRole, int $targetId): void
    {
        $this->change($actorId, $actorRole, $targetId, AccountStatus::SUSPENDED);
    }

    public function restore(int $actorId, string $actorRole, int $targetId): void
    {
        $this->change($actorId, $actorRole, $targetId, AccountStatus::ENABLED);
    }

    private function change(int $actorId, string $actorRole, int $targetId, string $status): void
    {
        if ($actorRole !== 'Super Admin') {
            throw new DomainException(lang('Accounts.accessDenied'));
        }
        if ($targetId < 1) {
            throw new DomainException(lang('Accounts.accountMissing'));
        }
        if ($targetId === $actorId) {
            throw new DomainException(lang('Accounts.ownStatus'));
        }

        $target = $this->accounts->find($targetId);
        if (! $target) {
            throw new DomainException(lang('Accounts.accountMissing'));
        }
        if ((AccountStatus::isSuspended($target) ? AccountStatus::SUSPENDED : AccountStatus::ENABLED) === $status) {
            throw new DomainException($status === AccountStatus::SUSPENDED ? lang('Accounts.alreadySuspended') : lang('Accounts.alreadyActive'));
        }

        $this->accounts->setStatus($targetId, $status);
    }
}
