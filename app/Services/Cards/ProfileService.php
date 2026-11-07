<?php

declare(strict_types=1);

namespace App\Services\Cards;

use App\Exceptions\FieldException;
use App\Repositories\ProfileRepository;
use App\Services\Uploads\PublicImageStorage;
use CodeIgniter\HTTP\Files\UploadedFile;
use DomainException;
use Throwable;

final class ProfileService
{
    public function __construct(private ?ProfileRepository $profiles = null, private ?PublicImageStorage $storage = null)
    {
        $this->profiles ??= new ProfileRepository();
        $this->storage ??= new PublicImageStorage();
    }

    private const SOCIAL_CHANNELS = ['instagram', 'facebook', 'twitter', 'tiktok', 'website'];

    /**
     * Data for the owner's Personal Info page, or null when the profile has not been created yet.
     *
     * @return array{userPersonal: array, socialMediaLinks: array<string, ?string>, images: list<array>}|null
     */
    public function pageFor(int $userId): ?array
    {
        $profile = $userId > 0 ? $this->profiles->findForUser($userId) : null;
        if (!$profile) {
            return null;
        }
        $profile['profile_image'] = CardPresentation::imageName($profile['profile_image'] ?? null);

        $links = $this->profiles->socialLinksForProfile((int) $profile['id_data']) ?? [];
        $socialMediaLinks = [];
        foreach (self::SOCIAL_CHANNELS as $channel) {
            $socialMediaLinks[$channel] = $links[$channel] ?? null;
        }

        $images = [];
        foreach ($this->profiles->galleryForProfile((int) $profile['id_data']) as $image) {
            $image['images'] = CardPresentation::imageName($image['images'] ?? null);
            $images[] = $image;
        }

        $profile['gallery_columns'] = CardPresentation::galleryColumns($profile);

        return ['userPersonal' => $profile, 'socialMediaLinks' => $socialMediaLinks, 'images' => $images];
    }

    public function setCardLocale(int $userId, string $locale): void
    {
        if (!in_array($locale, config('Locale')->supported, true)) {
            throw new FieldException('card_locale', lang('Profile.errCardLocale'));
        }
        if ($userId < 1 || $this->profiles->findForUser($userId) === null) {
            throw new DomainException(lang('Profile.needProfile'));
        }
        if (!$this->profiles->saveCardLocaleForUser($userId, $locale)) {
            throw new \RuntimeException('Card language could not be saved.');
        }
    }

    public function save(int $userId, array $input, ?UploadedFile $image): void
    {
        if ($userId < 1) {
            throw new DomainException(lang('Profile.accountMissing'));
        }

        $name = trim((string) ($input['nama'] ?? ''));
        $company = trim((string) ($input['company_name'] ?? ''));
        // Optional; empty means the card simply shows no title (PRD BR-02).
        $jobTitle = trim(preg_replace('/\s+/u', ' ', (string) ($input['job_title'] ?? '')) ?? '');
        $phone = trim((string) ($input['no_hp'] ?? ''));
        $whatsapp = trim((string) ($input['no_whatsapp'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $address = trim((string) ($input['address'] ?? ''));
        $map = CardPresentation::webUrl((string) ($input['google_map_link'] ?? ''));
        // Language of the public card (PRD LANG-02); absent from older forms, so only set when sent.
        $cardLocale = array_key_exists('card_locale', $input) ? (string) $input['card_locale'] : null;

        $validPhone = static fn (string $value): bool => preg_match('/\A[+0-9 ().-]+\z/D', $value) === 1 && strlen(CardPresentation::phone($value)) >= 7;
        // First failing field wins, in form order, so the message points at one input (FB-02).
        $checks = [
            'nama' => [$name !== '' && mb_strlen($name) <= 255, lang('Profile.errName')],
            'company_name' => [$company !== '' && mb_strlen($company) <= 5000, lang('Profile.errCompany')],
            'job_title' => [mb_strlen($jobTitle) <= 100, lang('Profile.errJobTitle')],
            'no_hp' => [$validPhone($phone), lang('Profile.errPhone')],
            'no_whatsapp' => [$validPhone($whatsapp) && strlen($whatsapp) <= 20, lang('Profile.errWhatsapp')],
            'email' => [filter_var($email, FILTER_VALIDATE_EMAIL) !== false && strlen($email) <= 255, lang('Profile.errEmail')],
            'google_map_link' => [$map !== null && strlen($map) <= 2048, lang('Profile.errMap')],
            'address' => [$address !== '' && mb_strlen($address) <= 5000, lang('Profile.errAddress')],
            'card_locale' => [$cardLocale === null || in_array($cardLocale, config('Locale')->supported, true), lang('Profile.errCardLocale')],
        ];
        foreach ($checks as $field => [$ok, $message]) {
            if (!$ok) {
                throw new FieldException($field, $message);
            }
        }

        $data = [
            'nama' => $name,
            'company_name' => $company,
            'job_title' => $jobTitle === '' ? null : $jobTitle,
            'no_hp' => $phone,
            'no_whatsapp' => $whatsapp,
            'email' => $email,
            'address' => $address,
            'google_map_link' => $map,
        ] + ($cardLocale === null ? [] : ['card_locale' => $cardLocale]);
        $previous = $this->profiles->findForUser($userId);
        $newImage = null;
        try {
            if ($image && $image->getError() !== UPLOAD_ERR_NO_FILE) {
                $newImage = $this->storage->store($image, 'profile_images');
                $data['profile_image'] = $newImage;
            }
            $this->profiles->saveForUser($userId, $data);
        } catch (Throwable $exception) {
            if ($newImage !== null) {
                $this->storage->delete('profile_images', $newImage);
            }
            throw $exception;
        }

        $oldImage = CardPresentation::imageName($previous['profile_image'] ?? null);
        if ($newImage !== null && $oldImage !== null && $oldImage !== $newImage) {
            $this->storage->delete('profile_images', $oldImage);
        }
    }
}
