<?php

declare(strict_types=1);

namespace App\Services\Cards;

/**
 * Sample card data for previewing a template before it is saved (PRD MA-02). Synthetic only;
 * never a real account.
 */
final class TemplateSamples
{
    /**
     * @return array<string, mixed> the same keys as PublicCardService::present()['data']
     */
    public static function card(): array
    {
        $profile = [
            'nama' => 'Ari Contoh', 'company_name' => 'Usaha Contoh', 'no_hp' => '0812 0000 0000',
            'phone_number' => '6281200000000', 'no_whatsapp' => '6281200000000', 'email' => 'ari@example.com',
            'address' => 'Alamat usaha contoh', 'google_map_link' => 'https://maps.example.com', 'profile_image' => null,
            'job_title' => 'Pemilik Usaha',
        ];
        $social = ['instagram' => 'https://instagram.example.com', 'facebook' => null, 'twitter' => null, 'tiktok' => null, 'website' => 'https://example.com'];

        return [
            'layout' => 'custom',
            'user' => ['id_user' => 0, 'username' => 'contoh'],
            'dataPersonal' => $profile,
            'images' => [],
            'socialMediaLinks' => $social,
            'galleryColumns' => 1,
            'vcard' => CardPresentation::vcard($profile, $social),
            'shareUrl' => base_url('contoh'),
            'eventUrl' => null,
            'eventToken' => null,
        ];
    }
}
