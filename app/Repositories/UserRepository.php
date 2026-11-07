<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\SocialMediaModel;
use App\Models\UserModel;
use App\Models\UserPersonalModel;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;
use Throwable;

class UserRepository
{
    public function __construct(
        private ?UserModel $users = null,
        private ?UserPersonalModel $profiles = null,
        private ?SocialMediaModel $socialMedia = null,
        private ?BaseConnection $db = null,
        private ?CardAliasRepository $aliases = null,
    ) {
        $this->users ??= new UserModel();
        $this->profiles ??= new UserPersonalModel();
        $this->socialMedia ??= new SocialMediaModel();
        $this->db ??= db_connect();
        $this->aliases ??= new CardAliasRepository($this->db);
    }

    /**
     * Customers with their latest subscription end (UTC, null when none) as `ends_at`.
     */
    public function allCustomers(): array
    {
        return $this->users
            ->select('table_user.id_user, username, email, registration_date, access_level, account_status')
            ->select('(SELECT MAX(s.ends_at) FROM subscriptions s WHERE s.user_id = table_user.id_user AND s.revoked_at IS NULL) AS ends_at', false)
            ->where('access_level', 'User')
            ->findAll();
    }

    public function findCustomer(int $id): ?array
    {
        return $this->users
            ->select('id_user, username, email, registration_date, expired_date, access_level, account_status')
            ->where('id_user', $id)
            ->where('access_level', 'User')
            ->first();
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

    public function createCustomer(array $data): int
    {
        $this->db->transBegin();

        try {
            // expired_date '0000-00-00' (never active) is a zero date that strict sql_mode rejects;
            // relax it for this one insert only, as SubscriptionRepository does for its sync.
            $this->db->query("SET @nfc_prev_mode = @@SESSION.sql_mode, SESSION sql_mode = REPLACE(REPLACE(@@SESSION.sql_mode, 'NO_ZERO_DATE', ''), 'NO_ZERO_IN_DATE', '')");

            try {
                $id = $this->users->insert($data, true);
            } finally {
                $this->db->query('SET SESSION sql_mode = @nfc_prev_mode');
            }
            if (! $id) {
                throw new RuntimeException('User gagal disimpan.');
            }

            $profileId = $this->profiles->insert([
                'id_user'      => $id,
                'nama'         => '',
                'company_name' => '',
            ], true);
            if (! $profileId || ! $this->socialMedia->insert(['id_data' => $profileId])) {
                throw new RuntimeException('Profil user gagal disimpan.');
            }

            if ($this->db->transStatus() === false) {
                throw new RuntimeException('User gagal disimpan.');
            }

            $this->db->transCommit();

            return (int) $id;
        } catch (Throwable $exception) {
            $this->db->transRollback();

            throw $exception;
        }
    }

    /**
     * Updates a customer; a new username keeps the old one as a card alias in the same transaction.
     */
    public function updateCustomer(int $id, array $data, ?string $previousUsername = null): bool
    {
        $this->db->transBegin();

        try {
            $updated = $this->users->revokingSessionsIf($data)->where('access_level', 'User')->update($id, $data);
            if ($updated && $previousUsername !== null && isset($data['username']) && $data['username'] !== $previousUsername) {
                $this->aliases->rememberRename($id, $previousUsername, $data['username']);
            }
            if (! $updated || $this->db->transStatus() === false) {
                $this->db->transRollback();

                return false;
            }
            $this->db->transCommit();

            return true;
        } catch (Throwable $exception) {
            $this->db->transRollback();

            throw $exception;
        }
    }

    public function deleteCustomer(int $id): bool
    {
        // Foreign keys in the existing schema cascade profile and social links.
        return $this->users->where('access_level', 'User')->delete($id);
    }
}
