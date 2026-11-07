<?php

declare(strict_types=1);

use App\Exceptions\FieldException;
use App\Libraries\QrCode;
use App\Repositories\AccountRepository;
use App\Repositories\CardAliasRepository;
use App\Repositories\ProfileRepository;
use App\Repositories\TemplateRepository;
use App\Services\Cards\PublicCardService;
use App\Services\Cards\SubscriptionAccessService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../_support/Fakes/PeriodListRepository.php';

/**
 * @internal
 */
final class PublicCardServiceTest extends TestCase
{
    public function testStatesAndNoProfileDataForNotices(): void
    {
        $service = $this->service();
        $now     = new DateTimeImmutable('2026-10-10 05:00:00', new DateTimeZone('UTC'));

        $this->assertSame(['state' => 'not_found'], $service->forUsername('nobody', $now));
        $this->assertSame(['state' => 'suspended'], $service->forUsername('sus', $now));
        $this->assertSame(['state' => 'inactive'], $service->forUsername('expired', $now));
        $this->assertSame(['state' => 'not_found'], $service->forUsername('noprofile', $now));
    }

    public function testOldUsernameRedirectsToCurrentOne(): void
    {
        $now = new DateTimeImmutable('2026-10-10 05:00:00', new DateTimeZone('UTC'));

        $this->assertSame(['state' => 'moved', 'username' => 'ada'], $this->service()->forUsername('ada-lama', $now));
        // Alias whose account was deleted in the meantime: nothing to show.
        $this->assertSame(['state' => 'not_found'], $this->service()->forUsername('hilang', $now));
    }

    public function testCustomTemplateWithoutVersionIsNotShownAndTamperedVersionIsRejected(): void
    {
        $service = $this->service();
        $user    = ['id_user' => 7, 'username' => 'ada', 'access_level' => 'User'];
        $profile = ['id_data' => 3, 'id_user' => 7, 'nama' => 'Ada', 'templates' => 'custom-9'];

        $this->assertNull($service->present($user, $profile, 'custom-9'));
        $this->expectException(FieldException::class);
        $service->present($user, $profile, 'custom-8');
    }

    public function testActiveCardIsSanitised(): void
    {
        $card = $this->service()->forUsername('ada', new DateTimeImmutable('2026-10-10 05:00:00', new DateTimeZone('UTC')));

        $this->assertSame('show', $card['state']);
        $this->assertSame('digital_card/classic', $card['view']);
        $this->assertSame('classic-white', $card['data']['layout']);
        $this->assertNull($card['data']['dataPersonal']['google_map_link']);
        $this->assertNull($card['data']['dataPersonal']['profile_image']);
        $this->assertSame('', $card['data']['dataPersonal']['email']);
        $this->assertNull($card['data']['socialMediaLinks']['website']);
        $this->assertSame('https://ig.example.test', $card['data']['socialMediaLinks']['instagram']);
        $this->assertNull($card['data']['images'][0]['images']);
        $this->assertSame('x', $card['data']['images'][0]['caption']);
        // Blank or whitespace-only captions become '' so no empty caption box is rendered.
        $this->assertSame('', $card['data']['images'][1]['caption']);
        $this->assertSame('', $card['data']['images'][2]['caption']);
        $this->assertSame(2, $card['data']['galleryColumns']);
    }

    public function testCardLanguageNeverFallsBackToDashboardOrVisitorLanguage(): void
    {
        $language = service('language');
        $before   = $language->getLocale();

        try {
            foreach (['id', 'en'] as $dashboardLocale) {
                $language->setLocale($dashboardLocale);

                foreach ([[], ['card_locale' => null], ['card_locale' => ''], ['card_locale' => 'fr']] as $profile) {
                    $this->assertSame('id', PublicCardService::cardLocale($profile));
                }
                $this->assertSame('en', PublicCardService::cardLocale(['card_locale' => 'en']));
                $this->assertSame('id', PublicCardService::cardLocale(['card_locale' => 'id']));
                $card = $this->service()->forUsername('ada', new DateTimeImmutable('2026-10-10 05:00:00 UTC'));
                $this->assertSame('id', $card['data']['cardLocale']);
                $share = $this->service('pro')->shareFor('ada', new DateTimeImmutable('2026-10-10 05:00:00 UTC'));
                $this->assertSame('id', $share['data']['cardLocale']);
            }
        } finally {
            $language->setLocale($before);
        }
    }

    public function testSharePageOnlyForActiveProAndQrHoldsTheCanonicalUrl(): void
    {
        $now   = new DateTimeImmutable('2026-10-10 05:00:00', new DateTimeZone('UTC'));
        $share = $this->service('pro')->shareFor('ada', $now);
        $this->assertSame(PublicCardService::SHOW, $share['state']);
        $this->assertSame(base_url('ada'), $share['data']['cardUrl']);
        $this->assertSame(QrCode::encode(base_url('ada'))->rows(), $share['data']['qr']->rows());
        $this->assertArrayNotHasKey('email', $share['data'], 'The share page carries no contact data.');

        // Account 7 ("ada") is an active Basic card in these fakes: no share page, no profile data.
        $this->assertSame(['state' => PublicCardService::NOT_FOUND], $this->service()->shareFor('ada', new DateTimeImmutable('2026-10-10 05:00:00', new DateTimeZone('UTC'))));
        // Old username redirects to the canonical share URL, carrying only the new username.
        $this->assertSame(['state' => PublicCardService::MOVED, 'username' => 'ada'], $this->service()->shareFor('ada-lama', new DateTimeImmutable('2026-10-10 05:00:00', new DateTimeZone('UTC'))));
    }

    private function service(string $plan = 'basic'): PublicCardService
    {
        $periods = [7 => [['2026-01-01 00:00:00', '2027-01-01 00:00:00']], 10 => [['2026-01-01 00:00:00', '2026-02-01 00:00:00']], 11 => [['2026-01-01 00:00:00', '2027-01-01 00:00:00']]];

        $aliases = new class () extends CardAliasRepository {
            public function __construct()
            {
            }

            public function userIdForAlias(string $alias): ?int
            {
                return ['ada-lama' => 7, 'hilang' => 99][$alias] ?? null;
            }
        };
        $accounts = new class () extends AccountRepository {
            public function __construct()
            {
            }

            public function find(int $id): ?array
            {
                return $id === 7 ? ['id_user' => 7, 'username' => 'ada', 'access_level' => 'User'] : null;
            }
        };

        $templates = new class () extends TemplateRepository {
            public function __construct()
            {
            }

            public function currentHtml(int $templateId): ?string
            {
                // A stored version that would no longer pass the sanitizer (tampered row).
                return $templateId === 8 ? '<div onclick="x()">{{name}}</div>' : null;
            }
        };

        return new PublicCardService(new CardProfiles(), null, new SubscriptionAccessService($plan === 'pro' ? new class ($periods) extends PeriodListRepository {
            public function currentForUser(int $userId, string $nowUtc): ?array
            {
                $row = parent::currentForUser($userId, $nowUtc);

                return $row === null ? null : ['plan_code' => 'pro'] + $row;
            }
        } : new PeriodListRepository($periods)), $aliases, $accounts, $templates);
    }
}

class CardProfiles extends ProfileRepository
{
    private array $accounts = [
        'ada'       => ['id_user' => 7, 'username' => 'ada', 'access_level' => 'User'],
        'sus'       => ['id_user' => 9, 'username' => 'sus', 'access_level' => 'User', 'account_status' => 'suspended'],
        'expired'   => ['id_user' => 10, 'username' => 'expired', 'access_level' => 'User'],
        'noprofile' => ['id_user' => 11, 'username' => 'noprofile', 'access_level' => 'User'],
    ];

    public function __construct()
    {
    }

    public function findAccountByUsername(string $username): ?array
    {
        return $this->accounts[$username] ?? null;
    }

    public function findForUser(int $userId): ?array
    {
        return $userId === 7 ? ['id_data' => 3, 'id_user' => 7, 'nama' => 'Ada', 'no_hp' => '0812', 'email' => 'not-an-email', 'google_map_link' => 'javascript:alert(1)', 'profile_image' => '../x.php', 'templates' => 'index-white.php', 'gallery_columns' => 2] : null;
    }

    public function galleryForProfile(int $profileId): array
    {
        return [
            ['id_images' => 1, 'images' => '../evil.php', 'caption' => ' x '],
            ['id_images' => 2, 'images' => 'a.webp', 'caption' => '   '],
            ['id_images' => 3, 'images' => 'b.webp', 'caption' => null],
        ];
    }

    public function socialLinksForProfile(int $profileId): ?array
    {
        return ['instagram' => 'https://ig.example.test', 'website' => 'javascript:alert(1)'];
    }
}
