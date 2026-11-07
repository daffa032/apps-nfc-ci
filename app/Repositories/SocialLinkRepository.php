<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\SocialMediaModel;
use App\Models\UserPersonalModel;

class SocialLinkRepository
{
    public function __construct(private ?UserPersonalModel $profiles = null, private ?SocialMediaModel $links = null)
    {
        $this->profiles ??= new UserPersonalModel();
        $this->links ??= new SocialMediaModel();
    }

    public function saveForUser(int $userId, string $channel, ?string $url): bool
    {
        $profile = $this->profiles->where('id_user', $userId)->first();
        if (! $profile) {
            return false;
        }

        $social = $this->links->where('id_data', $profile['id_data'])->first();
        if ($social) {
            return $this->links->update($social['id_sosial_media'], [$channel => $url]);
        }

        return $url !== null && (bool) $this->links->insert(['id_data' => $profile['id_data'], $channel => $url]);
    }
}
