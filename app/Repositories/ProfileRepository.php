<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\ImageModel;
use App\Models\SocialMediaModel;
use App\Models\UserModel;
use App\Models\UserPersonalModel;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;
use Throwable;

class ProfileRepository
{
    public function __construct(
        private ?UserPersonalModel $profiles = null,
        private ?SocialMediaModel $social = null,
        private ?BaseConnection $db = null,
        private ?ImageModel $images = null
    ) {
        $this->profiles ??= new UserPersonalModel();
        $this->social ??= new SocialMediaModel();
        $this->db ??= db_connect();
        $this->images ??= new ImageModel();
    }

    public function findForUser(int $userId): ?array
    {
        return $this->profiles->where('id_user', $userId)->first();
    }

    /**
     * Account row for a public card URL. Usernames are unique in table_user.
     */
    public function findAccountByUsername(string $username): ?array
    {
        return (new UserModel())->where('username', $username)->first();
    }

    public function saveGalleryColumnsForUser(int $userId, int $columns): bool
    {
        $profile = $this->findForUser($userId);

        return $profile !== null
            && (bool) $this->profiles->where('id_user', $userId)->update($profile['id_data'], ['gallery_columns' => $columns]);
    }

    public function saveCardLocaleForUser(int $userId, string $locale): bool
    {
        $profile = $this->findForUser($userId);

        return $profile !== null
            && (bool) $this->profiles->where('id_user', $userId)->update($profile['id_data'], ['card_locale' => $locale]);
    }

    public function saveTemplateForUser(int $userId, string $templateFile): bool
    {
        $profile = $this->findForUser($userId);

        return $profile !== null
            && (bool) $this->profiles->where('id_user', $userId)->update($profile['id_data'], ['templates' => $templateFile]);
    }

    public function socialLinksForProfile(int $profileId): ?array
    {
        return $this->social->where('id_data', $profileId)->first();
    }

    public function galleryForProfile(int $profileId): array
    {
        return $this->images->where('id_data', $profileId)->findAll();
    }

    public function findGalleryImage(int $profileId, int $imageId): ?array
    {
        return $this->images->where('id_data', $profileId)->where('id_images', $imageId)->first();
    }

    /**
     * @param list<array{images: string, caption: string}> $rows
     */
    public function addGalleryImages(int $profileId, array $rows): void
    {
        $this->db->transBegin();
        try {
            foreach ($rows as $row) {
                if (!$this->images->insert(['id_data' => $profileId] + $row)) {
                    throw new RuntimeException('Foto gagal disimpan.');
                }
            }
            if ($this->db->transStatus() === false) {
                throw new RuntimeException('Foto gagal disimpan.');
            }
            $this->db->transCommit();
        } catch (Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
    }

    public function updateGalleryCaption(int $profileId, int $imageId, string $caption): bool
    {
        return (bool) $this->images->where('id_data', $profileId)->update($imageId, ['caption' => $caption]);
    }

    public function deleteGalleryImage(int $profileId, int $imageId): bool
    {
        return (bool) $this->images->where('id_data', $profileId)->delete($imageId);
    }

    public function saveForUser(int $userId, array $data): void
    {
        $profile = $this->findForUser($userId);
        if ($profile) {
            if (!$this->profiles->where('id_user', $userId)->update($profile['id_data'], $data)) {
                throw new RuntimeException('Profil gagal disimpan.');
            }
            return;
        }

        $this->db->transBegin();
        try {
            $profileId = $this->profiles->insert($data + ['id_user' => $userId], true);
            if (!$profileId || !$this->social->insert(['id_data' => $profileId]) || $this->db->transStatus() === false) {
                throw new RuntimeException('Profil gagal dibuat.');
            }
            $this->db->transCommit();
        } catch (Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
    }
}
