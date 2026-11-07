<?php

declare(strict_types=1);

namespace App\Services\Uploads;

use CodeIgniter\HTTP\Files\UploadedFile;
use InvalidArgumentException;
use RuntimeException;

/**
 * Stores profile and gallery photos under public/uploads (PRD MA-04, UX-03).
 *
 * Every upload is decoded from its content (not its name or browser MIME), limited in bytes and
 * pixels, rotated upright from EXIF, scaled down, and re-encoded to WebP. Only the re-encoded file
 * is kept, so nothing the browser sent (scripts, metadata, polyglots) reaches the public folder.
 * The file is written to a temporary name and moved into place only once encoding succeeded.
 */
class PublicImageStorage
{
    private const FOLDERS = ['profile_images', 'multiple_images', 'brand_assets'];
    private const MAX_BYTES = 5 * 1024 * 1024;
    /** Decoding a huge image can exhaust memory even when the file is small. */
    private const MAX_SOURCE_PIXELS = 40_000_000;
    private const MAX_EDGE = ['profile_images' => 800, 'multiple_images' => 1600, 'brand_assets' => 1600];
    private const WEBP_QUALITY = 82;

    public function store(UploadedFile $file, string $folder): string
    {
        if (!in_array($folder, self::FOLDERS, true)) {
            throw new InvalidArgumentException('Invalid upload destination.');
        }
        if (!$file->isValid() || $file->hasMoved() || $file->getSize() > self::MAX_BYTES) {
            throw new InvalidArgumentException(lang('Upload.imageRules'));
        }

        $source = $file->getTempName();
        $info = @getimagesize($source);
        $type = $info[2] ?? null;
        if ($info === false || !in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw new InvalidArgumentException(lang('Upload.imageRules'));
        }
        [$width, $height] = $info;
        if ($width < 1 || $height < 1 || $width * $height > self::MAX_SOURCE_PIXELS) {
            throw new InvalidArgumentException(lang('Upload.imageTooLarge'));
        }

        $image = $this->decode($source, $type);
        $image = $this->orient($image, $source, $type);
        $image = $this->scaleDown($image, self::MAX_EDGE[$folder]);

        $directory = ROOTPATH . 'public/uploads/' . $folder;
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new InvalidArgumentException(lang('Upload.folderMissing'));
        }

        $name = bin2hex(random_bytes(16)) . '.webp';
        $temporary = $directory . '/.' . $name . '.tmp';
        try {
            if (!imagewebp($image, $temporary, self::WEBP_QUALITY) || !rename($temporary, $directory . '/' . $name)) {
                throw new RuntimeException('WebP encoding failed.');
            }
        } catch (\Throwable $exception) {
            if (is_file($temporary)) {
                unlink($temporary);
            }
            throw new InvalidArgumentException(lang('Upload.processFailed'), 0, $exception);
        } finally {
            imagedestroy($image);
        }

        return $name;
    }

    public function delete(string $folder, string $name): void
    {
        if (!in_array($folder, self::FOLDERS, true) || basename($name) !== $name) {
            throw new InvalidArgumentException('Invalid stored image name.');
        }

        $path = ROOTPATH . 'public/uploads/' . $folder . '/' . $name;
        if (is_file($path) && !unlink($path)) {
            log_message('error', 'Unable to delete uploaded image: ' . $path);
        }
    }

    private function decode(string $path, int $type): \GdImage
    {
        $image = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            default => false,
        };
        if (!$image instanceof \GdImage) {
            throw new InvalidArgumentException(lang('Upload.unreadable'));
        }
        // Keep PNG/WebP transparency through resampling and encoding.
        imagealphablending($image, false);
        imagesavealpha($image, true);

        return $image;
    }

    /**
     * Phone photos are often stored sideways with an EXIF orientation flag; bake it in.
     */
    private function orient(\GdImage $image, string $path, int $type): \GdImage
    {
        if ($type !== IMAGETYPE_JPEG || !function_exists('exif_read_data')) {
            return $image;
        }
        $orientation = (int) (@exif_read_data($path)['Orientation'] ?? 1);
        $angle = match ($orientation) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };
        if ($angle === 0) {
            return $image;
        }
        $rotated = imagerotate($image, $angle, 0);
        if ($rotated instanceof \GdImage) {
            imagedestroy($image);

            return $rotated;
        }

        return $image;
    }

    private function scaleDown(\GdImage $image, int $maxEdge): \GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, $maxEdge / max($width, $height));
        if ($scale >= 1) {
            return $image;
        }

        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));
        $scaled = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($scaled, false);
        imagesavealpha($scaled, true);
        imagecopyresampled($scaled, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($image);

        return $scaled;
    }
}
