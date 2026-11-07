<?php

declare(strict_types=1);

namespace App\Services\Cards;

final class CardPresentation
{
    public const GALLERY_COLUMNS = [1, 2];

    /**
     * Gallery layout for a profile row; 1 (the original look) when missing or invalid.
     */
    public static function galleryColumns(array $profile): int
    {
        $columns = (int) ($profile['gallery_columns'] ?? 1);

        return in_array($columns, self::GALLERY_COLUMNS, true) ? $columns : 1;
    }

    public static function webUrl(?string $value): ?string
    {
        $url = trim($value ?? '');
        if ($url === '' || preg_match('/[\x00-\x1F\x7F]/', $url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https'], true) ? $url : null;
    }

    public static function phone(?string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value ?? '');
        if ($digits === '' || strlen($digits) > 15) {
            return '';
        }

        return str_starts_with($digits, '0') ? '62' . substr($digits, 1) : $digits;
    }

    public static function imageName(?string $value): ?string
    {
        $name = $value ?? '';
        if (!preg_match('/\A[a-zA-Z0-9._-]+\.(?:jpe?g|png|webp)\z/iD', $name) || str_contains($name, '..')) {
            return null;
        }

        return $name;
    }

    public static function vcard(array $profile, ?array $social): string
    {
        $fields = [
            'BEGIN:VCARD',
            'VERSION:3.0',
            'FN:' . self::vcardValue((string) ($profile['nama'] ?? '')),
        ];
        if (!empty($profile['company_name'])) {
            $fields[] = 'ORG:' . self::vcardValue((string) $profile['company_name']);
        }
        if (!empty($profile['job_title'])) {
            $fields[] = 'TITLE:' . self::vcardValue((string) $profile['job_title']);
        }
        if (!empty($profile['email'])) {
            $fields[] = 'EMAIL:' . self::vcardValue($profile['email']);
        }
        $phone = $profile['phone_number'] ?? $profile['no_whatsapp'] ?? '';
        if ($phone !== '') {
            $fields[] = 'TEL:+' . $phone;
        }
        if (!empty($social['website'])) {
            $fields[] = 'URL:' . self::vcardValue($social['website']);
        }
        $fields[] = 'END:VCARD';

        return implode("\r\n", $fields) . "\r\n";
    }

    private static function vcardValue(string $value): string
    {
        $value = str_replace(["\r\n", "\r", "\n"], ' ', $value);
        return str_replace(['\\', ';', ','], ['\\\\', '\\;', '\\,'], $value);
    }
}
