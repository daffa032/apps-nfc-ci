<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\UserModel;
use CodeIgniter\Database\BaseConnection;

class PasswordResetRepository
{
    public function __construct(private ?UserModel $users = null, private ?BaseConnection $db = null)
    {
        $this->users ??= new UserModel();
        $this->db ??= db_connect();
    }

    public function findByEmail(string $email): ?array
    {
        return $this->users->select('id_user, email')->where('email', $email)->first();
    }

    public function saveToken(int $userId, string $hash, string $expiresAt): bool
    {
        return $this->users->update($userId, [
            'reset_token' => $hash,
            'reset_expired_at' => $expiresAt,
        ]);
    }

    public function hasValidToken(string $hash, string $now): bool
    {
        return $this->users
            ->where('reset_token', $hash)
            ->where('reset_expired_at >=', $now)
            ->countAllResults() > 0;
    }

    public function consume(string $hash, string $now, string $passwordHash): bool
    {
        // A reset ends every session of the account, including one an attacker may hold.
        $this->db->table('table_user')
            ->where('reset_token', $hash)
            ->where('reset_expired_at >=', $now)
            ->set('session_version', 'session_version + 1', false)
            ->update([
                'password' => $passwordHash,
                'reset_token' => null,
                'reset_expired_at' => null,
            ]);

        return $this->db->affectedRows() === 1;
    }

    public function invalidate(string $hash): void
    {
        $this->db->table('table_user')->where('reset_token', $hash)->update([
            'reset_token' => null,
            'reset_expired_at' => null,
        ]);
    }
}
