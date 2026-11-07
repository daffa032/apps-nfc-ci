<?php

declare(strict_types=1);

use App\Services\Uploads\PublicImageStorage;
use CodeIgniter\HTTP\Files\UploadedFile;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class PublicImageStorageTest extends TestCase
{
    private array $created = [];

    protected function tearDown(): void
    {
        foreach ($this->created as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testValidImageIsReencodedAsScaledWebp(): void
    {
        foreach (['png' => 'imagepng', 'jpg' => 'imagejpeg', 'webp' => 'imagewebp'] as $ext => $encode) {
            $name            = (new PublicImageStorage())->store($this->upload($ext, $encode, 2400, 1200), 'profile_images');
            $stored          = ROOTPATH . 'public/uploads/profile_images/' . $name;
            $this->created[] = $stored;

            $this->assertMatchesRegularExpression('/\A[a-f0-9]{32}\.webp\z/', $name);
            $info = getimagesize($stored);
            $this->assertSame(IMAGETYPE_WEBP, $info[2], $ext);
            $this->assertSame([800, 400], [$info[0], $info[1]], $ext . ' scaled to the 800px profile edge');
        }
    }

    public function testFakeImageIsRejectedAndNothingIsStored(): void
    {
        $before = glob(ROOTPATH . 'public/uploads/multiple_images/*') ?: [];
        $path   = tempnam(sys_get_temp_dir(), 'up');
        file_put_contents($path, "<?php echo 'x';");
        $this->created[] = $path;

        try {
            (new PublicImageStorage())->store(new UploadedFile($path, 'photo.png', 'image/png', filesize($path), UPLOAD_ERR_OK), 'multiple_images');
            $this->fail('A PHP file named .png must be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Unggah gambar JPEG, PNG, atau WebP maksimal 5 MB.', $exception->getMessage());
        }
        $this->assertSame($before, glob(ROOTPATH . 'public/uploads/multiple_images/*') ?: []);
    }

    public function testUnknownFolderIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new PublicImageStorage())->store($this->upload('png', 'imagepng', 10, 10), '../etc');
    }

    private function upload(string $ext, callable $encode, int $width, int $height): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 30, 120, 200));
        $path = tempnam(sys_get_temp_dir(), 'up');
        $encode($image, $path);
        imagedestroy($image);
        $this->created[] = $path;

        return new class ($path, 'photo.' . $ext, 'image/' . $ext, filesize($path), UPLOAD_ERR_OK) extends UploadedFile {
            public function isValid(): bool
            {
                // Test files are not real HTTP uploads, so is_uploaded_file() would reject them.
                return $this->error === UPLOAD_ERR_OK;
            }
        };
    }
}
