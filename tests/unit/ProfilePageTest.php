<?php

declare(strict_types=1);

use App\Repositories\ProfileRepository;
use App\Services\Cards\GalleryService;
use App\Services\Cards\ProfileService;
use App\Services\Uploads\PublicImageStorage;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class ProfilePageTest extends TestCase
{
    public function testPageIsNullWithoutProfile(): void
    {
        $this->assertNull((new ProfileService(new ProfilePageRepository(null)))->pageFor(7));
        $this->assertNull((new ProfileService(new ProfilePageRepository($this->profile())))->pageFor(0));
    }

    public function testPageReadsOwnProfileAndFillsEveryChannel(): void
    {
        $repository          = new ProfilePageRepository($this->profile(), ['instagram' => 'https://ig.example.test', 'id_data' => 3]);
        $repository->gallery = [
            ['id_images' => 5, 'images' => 'a.webp', 'caption' => 'ok'],
            ['id_images' => 6, 'images' => '../secret.php', 'caption' => 'bad'],
        ];

        $page = (new ProfileService($repository))->pageFor(7);

        $this->assertSame([7], $repository->lookedUpUsers);
        $this->assertSame(['instagram', 'facebook', 'twitter', 'tiktok', 'website'], array_keys($page['socialMediaLinks']));
        $this->assertSame('https://ig.example.test', $page['socialMediaLinks']['instagram']);
        $this->assertNull($page['socialMediaLinks']['website']);
        $this->assertNull($page['userPersonal']['profile_image']);
        $this->assertSame('a.webp', $page['images'][0]['images']);
        $this->assertNull($page['images'][1]['images']);
    }

    public function testDeleteRejectsImageOutsideOwnProfile(): void
    {
        $repository = new ProfilePageRepository($this->profile());
        $storage    = new RecordingImageStorage();

        try {
            (new GalleryService($repository, $storage))->delete(7, 99);
            $this->fail('Image owned by another profile must not be deleted.');
        } catch (DomainException) {
            $this->assertSame([[3, 99]], $repository->galleryLookups);
            $this->assertSame([], $repository->deleted);
            $this->assertSame([], $storage->deleted);
        }
    }

    public function testDeleteRemovesRowThenFile(): void
    {
        $repository                 = new ProfilePageRepository($this->profile());
        $repository->ownedImages[5] = ['id_images' => 5, 'images' => 'a.webp'];
        $storage                    = new RecordingImageStorage();

        (new GalleryService($repository, $storage))->delete(7, 5);

        $this->assertSame([[3, 5]], $repository->deleted);
        $this->assertSame([['multiple_images', 'a.webp']], $storage->deleted);
    }

    public function testUploadWithoutProfileOrFilesIsRejected(): void
    {
        $this->expectException(DomainException::class);
        (new GalleryService(new ProfilePageRepository(null), new RecordingImageStorage()))->upload(7, [], []);
    }

    public function testGalleryColumnsAcceptOnlyOneOrTwo(): void
    {
        $repository = new ProfilePageRepository($this->profile());
        $service    = new GalleryService($repository, new RecordingImageStorage());

        foreach ([0, 3, -1] as $columns) {
            try {
                $service->setColumns(7, $columns);
                $this->fail($columns . ' columns must be rejected.');
            } catch (DomainException) {
            }
        }
        $this->assertSame([], $repository->columns);

        $service->setColumns(7, 1);
        $this->assertSame([[7, 1]], $repository->columns);
    }

    public function testPageDefaultsGalleryColumnsToOne(): void
    {
        $page = (new ProfileService(new ProfilePageRepository($this->profile())))->pageFor(7);
        $this->assertSame(1, $page['userPersonal']['gallery_columns']);

        $page = (new ProfileService(new ProfilePageRepository($this->profile() + ['gallery_columns' => 9])))->pageFor(7);
        $this->assertSame(1, $page['userPersonal']['gallery_columns']);

        $page = (new ProfileService(new ProfilePageRepository($this->profile() + ['gallery_columns' => 2])))->pageFor(7);
        $this->assertSame(2, $page['userPersonal']['gallery_columns']);
    }

    public function testCaptionIsScopedToOwnProfileAndLimited(): void
    {
        $repository                 = new ProfilePageRepository($this->profile());
        $repository->ownedImages[5] = ['id_images' => 5, 'images' => 'a.webp'];
        $service                    = new GalleryService($repository, new RecordingImageStorage());

        foreach ([[99, 'x', 'Foto tidak ditemukan.'], [5, str_repeat('a', 256), 'Keterangan maksimal 255 karakter.']] as [$id, $caption, $message]) {
            try {
                $service->updateCaption(7, $id, $caption);
                $this->fail('Caption update must be rejected.');
            } catch (DomainException $exception) {
                $this->assertSame($message, $exception->getMessage());
            }
        }
        $this->assertSame([], $repository->captions);

        $service->updateCaption(7, 5, '  Toko kami  ');
        $this->assertSame([[3, 5, 'Toko kami']], $repository->captions);
    }

    private function profile(): array
    {
        return ['id_data' => 3, 'id_user' => 7, 'nama' => 'Ada', 'profile_image' => '../x.php'];
    }
}

class ProfilePageRepository extends ProfileRepository
{
    public array $lookedUpUsers  = [];
    public array $gallery        = [];
    public array $ownedImages    = [];
    public array $galleryLookups = [];
    public array $deleted        = [];
    public array $columns        = [];
    public array $captions       = [];

    public function __construct(private ?array $profile, private ?array $links = null)
    {
    }

    public function findForUser(int $userId): ?array
    {
        $this->lookedUpUsers[] = $userId;

        return $this->profile;
    }

    public function socialLinksForProfile(int $profileId): ?array
    {
        return $this->links;
    }

    public function galleryForProfile(int $profileId): array
    {
        return $this->gallery;
    }

    public function findGalleryImage(int $profileId, int $imageId): ?array
    {
        $this->galleryLookups[] = [$profileId, $imageId];

        return $this->ownedImages[$imageId] ?? null;
    }

    public function updateGalleryCaption(int $profileId, int $imageId, string $caption): bool
    {
        $this->captions[] = [$profileId, $imageId, $caption];

        return true;
    }

    public function saveGalleryColumnsForUser(int $userId, int $columns): bool
    {
        $this->columns[] = [$userId, $columns];

        return true;
    }

    public function deleteGalleryImage(int $profileId, int $imageId): bool
    {
        $this->deleted[] = [$profileId, $imageId];

        return true;
    }
}

class RecordingImageStorage extends PublicImageStorage
{
    public array $deleted = [];

    public function delete(string $folder, string $name): void
    {
        $this->deleted[] = [$folder, $name];
    }
}
