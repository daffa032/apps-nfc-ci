<?php

declare(strict_types=1);

namespace App\Services\Cards;

use App\Repositories\ProfileRepository;
use App\Services\Uploads\PublicImageStorage;
use CodeIgniter\HTTP\Files\UploadedFile;
use DomainException;
use RuntimeException;
use Throwable;

final class GalleryService
{
    private const FOLDER = 'multiple_images';

    public function __construct(private ?ProfileRepository $profiles = null, private ?PublicImageStorage $storage = null)
    {
        $this->profiles ??= new ProfileRepository();
        $this->storage ??= new PublicImageStorage();
    }

    /**
     * Stores every file and its caption for the owner's profile, or nothing at all.
     *
     * @param list<UploadedFile> $files
     * @param array<int, mixed>  $captions
     */
    public function upload(int $userId, array $files, array $captions): void
    {
        $profileId = $this->ownedProfileId($userId);
        if ($files === [] || $files[0]->getError() === UPLOAD_ERR_NO_FILE) {
            throw new DomainException(lang('Profile.noPhoto'));
        }

        $stored = [];

        try {
            $rows = [];

            foreach ($files as $index => $file) {
                $name     = $this->storage->store($file, self::FOLDER);
                $stored[] = $name;
                $rows[]   = ['images' => $name, 'caption' => (string) ($captions[$index] ?? '')];
            }
            $this->profiles->addGalleryImages($profileId, $rows);
        } catch (Throwable $exception) {
            foreach ($stored as $name) {
                $this->storage->delete(self::FOLDER, $name);
            }

            throw $exception;
        }
    }

    public function delete(int $userId, int $imageId): void
    {
        $profileId = $this->ownedProfileId($userId);
        $image     = $imageId > 0 ? $this->profiles->findGalleryImage($profileId, $imageId) : null;
        if (! $image) {
            throw new DomainException(lang('Profile.photoMissing'));
        }
        if (! $this->profiles->deleteGalleryImage($profileId, $imageId)) {
            throw new RuntimeException(lang('Profile.photoDeleteFailed'));
        }

        $name = CardPresentation::imageName($image['images'] ?? null);
        if ($name !== null) {
            $this->storage->delete(self::FOLDER, $name);
        }
    }

    /**
     * Changes the caption of one photo on the owner's own profile (PRD GAL-01).
     */
    public function updateCaption(int $userId, int $imageId, string $caption): void
    {
        $profileId = $this->ownedProfileId($userId);
        $caption   = trim($caption);
        if (mb_strlen($caption) > 255) {
            throw new DomainException(lang('Profile.captionTooLong'));
        }
        if ($imageId < 1 || ! $this->profiles->findGalleryImage($profileId, $imageId)) {
            throw new DomainException(lang('Profile.photoMissing'));
        }
        if (! $this->profiles->updateGalleryCaption($profileId, $imageId, $caption)) {
            throw new RuntimeException(lang('Profile.captionFailed'));
        }
    }

    /**
     * Stores the owner's gallery layout: 1 or 2 photos per row on every public template.
     */
    public function setColumns(int $userId, int $columns): void
    {
        if (! in_array($columns, CardPresentation::GALLERY_COLUMNS, true)) {
            throw new DomainException(lang('Profile.layoutInvalid'));
        }
        $this->ownedProfileId($userId);
        if (! $this->profiles->saveGalleryColumnsForUser($userId, $columns)) {
            throw new RuntimeException(lang('Profile.layoutFailed'));
        }
    }

    private function ownedProfileId(int $userId): int
    {
        $profile = $userId > 0 ? $this->profiles->findForUser($userId) : null;
        if (! $profile) {
            throw new DomainException(lang('Profile.profileMissing'));
        }

        return (int) $profile['id_data'];
    }
}
