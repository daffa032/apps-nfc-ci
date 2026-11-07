<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\UserModel;
use App\Services\Users\AccountStatus;
use CodeIgniter\Database\BaseConnection;
use DomainException;
use RuntimeException;
use Throwable;

/**
 * Suspends or restores any account and revokes its sessions in the same UPDATE.
 * The last enabled Super Admin is protected with row locks so two requests cannot both pass.
 */
class AccountStatusRepository
{
    public function __construct(private ?UserModel $users = null, private ?BaseConnection $db = null)
    {
        $this->users ??= new UserModel();
        $this->db ??= db_connect();
    }

    public function find(int $id): ?array
    {
        return $this->users->find($id);
    }

    public function setStatus(int $id, string $status): void
    {
        $this->db->transBegin();

        try {
            $enabledSuperAdmins = $this->lockEnabledSuperAdmins();
            $target             = $this->users->find($id);
            if (! $target) {
                throw new DomainException(lang('Accounts.accountMissing'));
            }
            if ($status === AccountStatus::SUSPENDED && $target['access_level'] === 'Super Admin'
                && in_array((string) $id, $enabledSuperAdmins, true) && count($enabledSuperAdmins) <= 1) {
                throw new DomainException(lang('Accounts.lastSuperSuspend'));
            }

            $changes = ['account_status' => $status];
            if (! $this->users->revokingSessionsIf($changes)->update($id, $changes) || $this->db->transStatus() === false) {
                throw new RuntimeException('Status akun gagal diperbarui.');
            }
            $this->db->transCommit();
        } catch (Throwable $exception) {
            $this->db->transRollback();

            throw $exception;
        }
    }

    /**
     * @return list<string>
     */
    private function lockEnabledSuperAdmins(): array
    {
        $table = $this->db->prefixTable('table_user');
        $rows  = $this->db->query(
            "SELECT id_user FROM `{$table}` WHERE access_level = ? AND account_status <> ? FOR UPDATE",
            ['Super Admin', AccountStatus::SUSPENDED],
        )->getResultArray();

        return array_map('strval', array_column($rows, 'id_user'));
    }
}
