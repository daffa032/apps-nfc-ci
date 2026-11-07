<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\UserModel;

class AccountRepository
{
    public function __construct(private ?UserModel $users = null)
    {
        $this->users ??= new UserModel();
    }

    public function find(int $id): ?array
    {
        return $this->users->find($id);
    }

    public function emailTaken(string $email, int $exceptId): bool
    {
        return $this->users->where('email', $email)->where('id_user !=', $exceptId)->countAllResults() > 0;
    }

    public function update(int $id, array $changes): bool
    {
        return $this->users->revokingSessionsIf($changes)->update($id, $changes);
    }
}
