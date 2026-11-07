<?php

declare(strict_types=1);

namespace App\Services\Cards;

use App\Repositories\SocialLinkRepository;
use DomainException;
use RuntimeException;

final class SocialLinkService
{
    private const CHANNELS = ['instagram', 'facebook', 'twitter', 'tiktok', 'website'];

    /** Matches the sosial_media column size (migration 2026-10-11-180000) and the form maxlength. */
    public const MAX_URL_LENGTH = 500;

    public function __construct(private ?SocialLinkRepository $links = null)
    {
        $this->links ??= new SocialLinkRepository();
    }

    public function save(int $userId, string $channel, string $url): void
    {
        $this->assertChannel($channel);
        $validated = CardPresentation::webUrl($url);
        if ($validated !== null && strlen($validated) > self::MAX_URL_LENGTH) {
            throw new DomainException(lang('Profile.linkTooLong'));
        }
        if ($validated === null) {
            throw new DomainException(lang('Profile.linkInvalid'));
        }
        if (!$this->links->saveForUser($userId, $channel, $validated)) {
            throw new RuntimeException(lang('Profile.linkSaveFailed'));
        }
    }

    public function delete(int $userId, string $channel): void
    {
        $this->assertChannel($channel);
        if (!$this->links->saveForUser($userId, $channel, null)) {
            throw new RuntimeException(lang('Profile.linkDeleteFailed'));
        }
    }

    private function assertChannel(string $channel): void
    {
        if (!in_array($channel, self::CHANNELS, true)) {
            throw new DomainException(lang('Profile.channelInvalid'));
        }
    }
}
