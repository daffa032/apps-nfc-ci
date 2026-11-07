<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\SocialMediaModel;
use App\Models\UserModel;
use App\Models\UserPersonalModel;
use CodeIgniter\Database\BaseConnection;
use DomainException;
use RuntimeException;
use Throwable;

class AdminRepository
{
    public function __construct(
        private ?UserModel $users = null,
        private ?UserPersonalModel $profiles = null,
        private ?SocialMediaModel $social = null,
        private ?BaseConnection $db = null,
        private ?CardAliasRepository $aliases = null,
    ) {
        $this->users ??= new UserModel();
        $this->profiles ??= new UserPersonalModel();
        $this->social ??= new SocialMediaModel();
        $this->db ??= db_connect();
        $this->aliases ??= new CardAliasRepository($this->db);
    }

    public function all(): array
    {
        return $this->users->select('id_user, username, email, registration_date, expired_date, access_level, account_status')
            ->whereIn('access_level', ['Admin', 'Super Admin'])->findAll();
    }

    public function find(int $id): ?array
    {
        return $this->users->where('id_user', $id)
            ->whereIn('access_level', ['Admin', 'Super Admin'])->first();
    }

    public function usernameTaken(string $username, ?int $exceptId = null): bool
    {
        $query = $this->users->where('username', $username);
        if ($exceptId !== null) {
            $query->where('id_user !=', $exceptId);
        }

        // A previous username of another account still opens that card (card_aliases).
        return $query->countAllResults() > 0 || $this->aliases->takenByOther($username, $exceptId);
    }

    public function emailTaken(string $email, ?int $exceptId = null): bool
    {
        $query = $this->users->where('email', $email);
        if ($exceptId !== null) {
            $query->where('id_user !=', $exceptId);
        }

        return $query->countAllResults() > 0;
    }

    public function create(array $data): int
    {
        $this->db->transBegin();

        try {
            $userId = $this->users->insert($data, true);
            if (! $userId) {
                throw new RuntimeException('Admin gagal dibuat.');
            }
            $profileId = $this->profiles->insert(['id_user' => $userId, 'nama' => '', 'company_name' => ''], true);
            if (! $profileId || ! $this->social->insert(['id_data' => $profileId])) {
                throw new RuntimeException('Profil admin gagal dibuat.');
            }
            if ($this->db->transStatus() === false) {
                throw new RuntimeException('Admin gagal dibuat.');
            }
            $this->db->transCommit();

            return (int) $userId;
        } catch (Throwable $exception) {
            $this->db->transRollback();

            throw $exception;
        }
    }

    public function update(int $id, array $changes): void
    {
        $this->db->transBegin();

        try {
            $superAdminIds = $this->lockSuperAdmins();
            $target        = $this->find($id);
            if (! $target) {
                throw new DomainException(lang('Accounts.adminMissing'));
            }
            if ($target['access_level'] === 'Super Admin'
                && $changes['access_level'] !== 'Super Admin'
                && count($superAdminIds) <= 1) {
                throw new DomainException(lang('Accounts.lastSuperDemote'));
            }
            // Compare against the stored role so an unchanged role does not sign the admin out.
            $sensitive = array_filter([
                'password'     => isset($changes['password']),
                'access_level' => ($changes['access_level'] ?? $target['access_level']) !== $target['access_level'],
            ]);
            if (! $this->users->revokingSessionsIf($sensitive)->whereIn('access_level', ['Admin', 'Super Admin'])->update($id, $changes)
                || $this->db->transStatus() === false) {
                throw new RuntimeException('Admin gagal diperbarui.');
            }
            if (isset($changes['username']) && $changes['username'] !== $target['username']) {
                $this->aliases->rememberRename($id, (string) $target['username'], $changes['username']);
            }
            $this->db->transCommit();
        } catch (Throwable $exception) {
            $this->db->transRollback();

            throw $exception;
        }
    }

    public function delete(int $id): void
    {
        $this->db->transBegin();

        try {
            $superAdminIds = $this->lockSuperAdmins();
            $target        = $this->find($id);
            if (! $target) {
                throw new DomainException(lang('Accounts.adminMissing'));
            }
            if ($target['access_level'] === 'Super Admin' && count($superAdminIds) <= 1) {
                throw new DomainException(lang('Accounts.lastSuperDelete'));
            }
            if (! $this->users->whereIn('access_level', ['Admin', 'Super Admin'])->delete($id)
                || $this->db->transStatus() === false) {
                throw new RuntimeException('Admin gagal dihapus.');
            }
            $this->db->transCommit();
        } catch (Throwable $exception) {
            $this->db->transRollback();

            throw $exception;
        }
    }

    private function lockSuperAdmins(): array
    {
        $table = $this->db->prefixTable('table_user');
        $rows  = $this->db->query("SELECT id_user FROM `{$table}` WHERE access_level = ? FOR UPDATE", ['Super Admin'])->getResultArray();

        return array_column($rows, 'id_user');
    }
}
