<?php

declare(strict_types=1);

use App\Repositories\SocialLinkRepository;
use App\Services\Cards\SocialLinkService;
use PHPUnit\Framework\TestCase;

final class SocialLinkServiceTest extends TestCase
{
    public function testRejectsUnlistedChannelBeforeWriting(): void
    {
        $repository = new InMemorySocialLinkRepository();
        $this->expectException(DomainException::class);
        try {
            (new SocialLinkService($repository))->save(1, 'id_data', 'https://example.test');
        } finally {
            self::assertSame([], $repository->writes);
        }
    }

    public function testRejectsScriptUrlAndSavesAllowedUrl(): void
    {
        $repository = new InMemorySocialLinkRepository();
        $service = new SocialLinkService($repository);
        try {
            $service->save(1, 'website', 'javascript:alert(1)');
            self::fail('Script URL must be rejected.');
        } catch (DomainException) {
            self::assertSame([], $repository->writes);
        }

        $service->save(1, 'website', 'https://example.test');
        self::assertSame([[1, 'website', 'https://example.test']], $repository->writes);
    }

    public function testUrlLongerThanColumnIsRejectedInsteadOfCut(): void
    {
        $repository = new InMemorySocialLinkRepository();
        $service = new SocialLinkService($repository);
        $long = 'https://example.test/' . str_repeat('a', 480);
        try {
            $service->save(1, 'facebook', $long);
            self::fail('URL over 500 characters must be rejected.');
        } catch (DomainException $exception) {
            self::assertSame('Tautan terlalu panjang (maksimal 500 karakter).', $exception->getMessage());
        }
        self::assertSame([], $repository->writes);

        $service->save(1, 'facebook', substr($long, 0, 500));
        self::assertSame(500, strlen($repository->writes[0][2]));
    }
}

class InMemorySocialLinkRepository extends SocialLinkRepository
{
    public array $writes = [];

    public function __construct()
    {
    }

    public function saveForUser(int $userId, string $channel, ?string $url): bool
    {
        $this->writes[] = [$userId, $channel, $url];
        return true;
    }
}
