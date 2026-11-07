<?php

declare(strict_types=1);

use App\Repositories\ProfileRepository;
use App\Services\Cards\ProfileService;
use PHPUnit\Framework\TestCase;

final class ProfileServiceTest extends TestCase
{
    public function testSaveUsesAuthenticatedUserIdAndValidatedFields(): void
    {
        $repository = new InMemoryProfileRepository();
        (new ProfileService($repository))->save(7, $this->validInput() + ['id_user' => 99], null);

        self::assertSame(7, $repository->savedUserId);
        self::assertArrayNotHasKey('id_user', $repository->savedData);
        self::assertSame('https://maps.example.test/place', $repository->savedData['google_map_link']);
    }

    public function testScriptMapUrlIsRejected(): void
    {
        $repository = new InMemoryProfileRepository();
        $this->expectException(DomainException::class);
        (new ProfileService($repository))->save(7, array_replace($this->validInput(), ['google_map_link' => 'javascript:alert(1)']), null);
    }

    public function testCardLanguageCanBeSavedWithoutPostingOtherProfileFields(): void
    {
        $repository = new InMemoryProfileRepository();
        $repository->profile = ['id_data' => 3, 'id_user' => 7, 'nama' => 'Ada'];
        $service = new ProfileService($repository);
        foreach (['en', 'id'] as $locale) {
            $service->setCardLocale(7, $locale);
            self::assertSame([7, $locale], $repository->savedLocale);
        }
        self::assertSame([], $repository->savedData);
    }

    public function testCardLanguageRejectsUnsupportedValuesAndMissingOwners(): void
    {
        $repository = new InMemoryProfileRepository();
        $service = new ProfileService($repository);
        foreach (['', 'fr', 'EN', '../en'] as $locale) {
            try {
                $service->setCardLocale(7, $locale);
                self::fail('Invalid language must be rejected.');
            } catch (App\Exceptions\FieldException $exception) {
                self::assertSame('card_locale', $exception->field);
            }
        }
        foreach ([0, 7] as $userId) {
            try {
                $service->setCardLocale($userId, 'en');
                self::fail('A missing profile must not be created by the language setting.');
            } catch (DomainException) {
                self::assertSame([], $repository->savedLocale);
            }
        }
    }

    public function testCardLanguageDoesNotReportSuccessWhenPersistenceFails(): void
    {
        $repository = new InMemoryProfileRepository();
        $repository->profile = ['id_data' => 3, 'id_user' => 7];
        $repository->localeSaveSucceeds = false;
        $this->expectException(RuntimeException::class);
        (new ProfileService($repository))->setCardLocale(7, 'en');
    }

    private function validInput(): array
    {
        return [
            'nama' => 'Ada',
            'company_name' => 'Lab',
            'no_hp' => '+628123456789',
            'no_whatsapp' => '+628123456789',
            'email' => 'ada@example.test',
            'address' => 'Main Street',
            'google_map_link' => 'https://maps.example.test/place',
        ];
    }
}

class InMemoryProfileRepository extends ProfileRepository
{
    public ?int $savedUserId = null;
    public array $savedData = [];
    public ?array $profile = null;
    public array $savedLocale = [];
    public bool $localeSaveSucceeds = true;

    public function __construct()
    {
    }

    public function findForUser(int $userId): ?array
    {
        return $this->profile;
    }

    public function saveCardLocaleForUser(int $userId, string $locale): bool
    {
        $this->savedLocale = [$userId, $locale];

        return $this->localeSaveSucceeds;
    }

    public function saveForUser(int $userId, array $data): void
    {
        $this->savedUserId = $userId;
        $this->savedData = $data;
    }
}
