<?php

declare(strict_types=1);

use App\Repositories\AdminRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\AccountStatusRepository;
use App\Repositories\CardAliasRepository;
use App\Repositories\CardAnalyticsRepository;
use App\Repositories\PlanRepository;
use App\Repositories\ProfileRepository;
use App\Repositories\SocialLinkRepository;
use App\Repositories\SubscriptionRepository;
use App\Repositories\TemplateRepository;
use App\Repositories\UserRepository;
use App\Services\Cards\SubscriptionGrantService;
use Tests\Support\RepositoryTestCase;

require_once __DIR__ . '/../_support/RepositoryTestCase.php';

/**
 * Scope, lock and atomicity of the repositories on the real schema (PRD ARC-02, ARC-03, ARC-05).
 *
 * @internal
 */
final class RepositoryIntegrationTest extends RepositoryTestCase
{
    public function testGalleryWritesAreScopedToTheOwnersProfile(): void
    {
        [, $ownProfile] = $this->makeAccount('owner1');
        [, $otherProfile] = $this->makeAccount('other1');
        $profiles = new ProfileRepository();
        $profiles->addGalleryImages($otherProfile, [['images' => 'a.webp', 'caption' => 'milik orang lain']]);
        $otherImage = $this->imageId('a.webp');

        self::assertNull($profiles->findGalleryImage($ownProfile, $otherImage));
        $profiles->updateGalleryCaption($ownProfile, $otherImage, 'diubah');
        $profiles->deleteGalleryImage($ownProfile, $otherImage);
        self::assertSame('milik orang lain', $this->db->table('table_multiple_images')->where('id_images', $otherImage)->get()->getRow()->caption);
    }

    public function testCardLanguageOnlyUpdatesTheOwnersProfileAndNotDashboardPreferences(): void
    {
        [$owner, $ownProfile] = $this->makeAccount('locale1');
        [$other, $otherProfile] = $this->makeAccount('locale2');
        $this->db->table('table_user')->where('id_user', $owner)->update(['preferred_locale' => 'id']);
        $this->db->table('table_data_personal')->where('id_data', $otherProfile)->update(['card_locale' => 'id']);
        $profiles = new ProfileRepository();

        self::assertTrue($profiles->saveCardLocaleForUser($owner, 'en'));
        self::assertSame('en', $profiles->findForUser($owner)['card_locale']);
        self::assertSame('id', $profiles->findForUser($other)['card_locale']);
        self::assertSame('Nama locale1', $profiles->findForUser($owner)['nama']);
        self::assertSame('id', $this->db->table('table_user')->where('id_user', $owner)->get()->getRow()->preferred_locale);
        self::assertFalse($profiles->saveCardLocaleForUser(999999, 'en'));
        self::assertSame(2, $this->db->table('table_data_personal')->countAllResults());
    }

    public function testCaptionUpTo255CharactersIsStoredWhole(): void
    {
        [, $profile] = $this->makeAccount('caption1');
        $profiles = new ProfileRepository();
        $profiles->addGalleryImages($profile, [['images' => 'b.webp', 'caption' => '']]);
        $id = $this->imageId('b.webp');
        $caption = str_repeat('k', 255);

        self::assertTrue($profiles->updateGalleryCaption($profile, $id, $caption));
        self::assertSame($caption, $this->db->table('table_multiple_images')->where('id_images', $id)->get()->getRow()->caption);
    }

    public function testDeletingAProfileCascadesToItsGallery(): void
    {
        [$user, $profile] = $this->makeAccount('cascade1');
        (new ProfileRepository())->addGalleryImages($profile, [['images' => 'a.webp', 'caption' => '']]);
        (new UserRepository())->deleteCustomer($user);

        self::assertSame(0, $this->db->table('table_multiple_images')->where('id_data', $profile)->countAllResults());
    }

    public function testSocialLinkOnlyWritesTheOwnersRowAndKeeps500Characters(): void
    {
        [$owner, $ownProfile] = $this->makeAccount('social1');
        [, $otherProfile] = $this->makeAccount('social2');
        $url = 'https://example.test/' . str_repeat('a', 479);

        self::assertTrue((new SocialLinkRepository())->saveForUser($owner, 'facebook', $url));
        self::assertSame($url, $this->db->table('sosial_media')->where('id_data', $ownProfile)->get()->getRow()->facebook);
        self::assertNull($this->db->table('sosial_media')->where('id_data', $otherProfile)->get()->getRow()->facebook);
    }

    public function testCustomerRepositoryNeverTouchesStaff(): void
    {
        [$admin] = $this->makeAccount('staff1', 'Admin');
        $users = new UserRepository();

        self::assertNull($users->findCustomer($admin));
        // CI4 Model::update() reports true even when the role filter matches no row, so check the data.
        $users->updateCustomer($admin, ['email' => 'x@example.test'], 'staff1');
        $users->deleteCustomer($admin);
        $row = $this->db->table('table_user')->where('id_user', $admin)->get()->getRow();
        self::assertSame('staff1@example.test', $row->email);
    }

    public function testCreatingCustomerWritesProfileAndSocialTogetherUnderStrictMode(): void
    {
        $id = (new UserRepository())->createCustomer([
            'username' => 'baru1', 'email' => 'baru1@example.test', 'password' => 'x',
            'registration_date' => '2026-10-11', 'expired_date' => '0000-00-00', 'access_level' => 'User',
        ]);

        $profile = $this->db->table('table_data_personal')->where('id_user', $id)->get()->getRow();
        self::assertNotNull($profile);
        self::assertSame(1, $this->db->table('sosial_media')->where('id_data', $profile->id_data)->countAllResults());
    }

    public function testRenameKeepsOldUsernameAsAliasAndBlocksItForOthers(): void
    {
        [$user] = $this->makeAccount('lama1');
        $users = new UserRepository();

        self::assertTrue($users->updateCustomer($user, ['username' => 'baru2', 'email' => 'lama1@example.test'], 'lama1'));
        self::assertSame($user, (new CardAliasRepository())->userIdForAlias('lama1'));
        self::assertSame($user, (new CardAliasRepository())->userIdForAlias('LAMA1'));
        self::assertTrue($users->usernameTaken('lama1', null));
        self::assertFalse($users->usernameTaken('lama1', $user));

        // Taking the old name back removes the alias and stores the newer one.
        self::assertTrue($users->updateCustomer($user, ['username' => 'lama1', 'email' => 'lama1@example.test'], 'baru2'));
        self::assertNull((new CardAliasRepository())->userIdForAlias('lama1'));
        self::assertSame($user, (new CardAliasRepository())->userIdForAlias('baru2'));
    }

    public function testFailedRenameLeavesNoAlias(): void
    {
        [$user] = $this->makeAccount('ganti1');
        $this->makeAccount('dipakai1');

        // Inside a transaction CI4 reports the unique-key error as false instead of throwing.
        try {
            $saved = (new UserRepository())->updateCustomer($user, ['username' => 'dipakai1', 'email' => 'ganti1@example.test'], 'ganti1');
        } catch (CodeIgniter\Database\Exceptions\DatabaseException) {
            $saved = false;
        }
        self::assertFalse($saved);
        self::assertNull((new CardAliasRepository())->userIdForAlias('ganti1'));
        self::assertSame('ganti1', $this->db->table('table_user')->where('id_user', $user)->get()->getRow()->username);
    }

    public function testGrantsDoNotOverlapAndRevokeKeepsHistory(): void
    {
        [$user] = $this->makeAccount('langganan1');
        $grants = new SubscriptionGrantService(new SubscriptionRepository());
        $now = new DateTimeImmutable('2026-10-11 03:00:00', new DateTimeZone('UTC'));

        $first = $grants->grant($user, 1, '+1 month', $now, 'Transfer bank');
        $second = $grants->grant($user, 1, '+1 month', $now, 'Perpanjangan');
        self::assertSame($first['ends_at'], $second['starts_at']);
        self::assertSame('Perpanjangan', $this->db->table('subscription_grants')->orderBy('id', 'DESC')->get()->getRow()->note);
        self::assertSame('2026-12-11', $this->db->table('table_user')->where('id_user', $user)->get()->getRow()->expired_date);

        $grants->revoke($user, $now);
        self::assertSame(2, $this->db->table('subscriptions')->where('user_id', $user)->countAllResults());
        self::assertNull((new SubscriptionRepository())->currentForUser($user, '2026-10-11 03:00:01'));
    }

    public function testLastEnabledSuperAdminCannotBeSuspendedOrDeleted(): void
    {
        [$super] = $this->makeAccount('super1', 'Super Admin');
        [$admin] = $this->makeAccount('admin1', 'Admin');

        try {
            (new AccountStatusRepository())->setStatus($super, 'suspended');
            self::fail('Last Super Admin must not be suspended.');
        } catch (DomainException) {
        }
        try {
            (new AdminRepository())->delete($super);
            self::fail('Last Super Admin must not be deleted.');
        } catch (DomainException) {
        }
        $before = (int) $this->db->table('table_user')->where('id_user', $admin)->get()->getRow()->session_version;
        (new AccountStatusRepository())->setStatus($admin, 'suspended');
        $row = $this->db->table('table_user')->where('id_user', $admin)->get()->getRow();
        self::assertSame(['suspended', $before + 1], [$row->account_status, (int) $row->session_version]);
    }

    public function testTemplateSlotCheckRollsBackOnRejection(): void
    {
        $templates = new TemplateRepository();
        $id = (int) $this->db->table('templates')->where('nama_file', 'layout-gelap')->get()->getRow()->id_template;
        try {
            $templates->updateLocked($id, ['is_active' => 1, 'plan_code' => 'basic'], static function (array $rows) use ($id): void {
                // The check sees the proposed change on the locked rows.
                $changed = array_values(array_filter($rows, static fn (array $row): bool => (int) $row['id_template'] === $id))[0];
                if ((int) $changed['is_active'] === 1) {
                    throw new DomainException('Slot penuh.');
                }
            });
            self::fail('The check must reject the change.');
        } catch (DomainException) {
        }

        $row = $this->db->table('templates')->where('id_template', $id)->get()->getRow();
        self::assertSame([0, 'pro'], [(int) $row->is_active, $row->plan_code]);
    }

    public function testUploadedTemplateCountsTowardsSlotsAndStoresImmutableVersion(): void
    {
        $repo = new TemplateRepository();
        $html = (new App\Services\Cards\TemplateHtmlSanitizer())->normalize((string) file_get_contents(APPPATH . 'Views/digital_card/sample-template.html'));
        $seen = [];
        $before = count(App\Services\Cards\TemplateCatalog::availableRows($repo->all()));
        $id = $repo->createUploaded('Rapi', 'pro', false, '', $html, 1, static function (array $rows) use (&$seen): void {
            $seen = App\Services\Cards\TemplateCatalog::availableRows($rows);
        });

        self::assertCount($before + 1, $seen, 'The candidate row must be visible to the slot check.');
        $row = $this->db->table('templates')->where('id_template', $id)->get()->getRow();
        self::assertSame('custom-' . $id, $row->nama_file);
        self::assertSame($html, $repo->currentHtml($id));
        self::assertSame(hash('sha256', $html), $this->db->table('template_versions')->where('template_id', $id)->get()->getRow()->checksum);

        $admin = new App\Services\Cards\TemplateAdminService($repo);
        $this->db->table('templates')->update(['is_active' => 1, 'plan_code' => 'pro']);
        $this->db->table('templates')->where('nama_file', 'layout-profesional')->update(['plan_code' => 'basic']);
        $this->db->table('templates')->where('id_template', $id)->update(['is_active' => 1]);
        // Fill up to the configured total (the built-ins plus uploads); the next one is refused.
        $limit = $repo->slotLimits()['total'];
        $active = $this->db->table('templates')->where('is_active', 1)->countAllResults();
        for ($i = $active; $i < $limit; $i++) {
            $admin->create('Isi' . $i, 'pro', true, '', $html, 1);
        }
        try {
            $admin->create('Kelebihan', 'pro', true, '', $html, 1);
            self::fail('One more active template than the limit must be rejected.');
        } catch (DomainException $exception) {
            self::assertStringContainsString('Slot penuh', $exception->getMessage());
        }
        self::assertSame(0, $this->db->table('templates')->where('nama_template', 'Kelebihan')->countAllResults());
        self::assertSame($limit, $this->db->table('templates')->where('is_active', 1)->countAllResults());
    }

    public function testPlanSaveCreatesNewVersion(): void
    {
        $plans = new PlanRepository();
        $before = (int) $this->db->table('plans')->where('code', 'pro')->get()->getRow()->version;

        $version = $plans->saveVersion('pro', 199000, 12, true, 1);
        self::assertSame($before + 1, $version);
        self::assertSame(1, $this->db->table('plan_price_versions')->where('version', $version)->countAllResults());
    }

    public function testAnalyticsCountsAreAtomicAndUniquePerDay(): void
    {
        [$card] = $this->makeAccount('kartu1');
        $metrics = new CardAnalyticsRepository();
        $metrics->recordView($card, '2026-10-11', str_repeat('a', 64));
        $metrics->recordView($card, '2026-10-11', str_repeat('a', 64));
        $metrics->recordView($card, '2026-10-11', str_repeat('b', 64));
        $metrics->recordContactClick($card, '2026-10-11');

        self::assertSame(['2026-10-11' => ['views' => 3, 'unique_visitors' => 2, 'contact_clicks' => 1]], $metrics->month($card, '2026-10-01', '2026-11-01'));
        self::assertSame(['visitors' => 2, 'metrics' => 1], $metrics->purge('2026-10-12', '2026-10-12'));
    }

    private function imageId(string $file): int
    {
        return (int) $this->db->table('table_multiple_images')->where('images', $file)->get()->getRow()->id_images;
    }

    public function testAuditRepositoryIsAppendOnly(): void
    {
        $methods = array_map(static fn (ReflectionMethod $m): string => $m->getName(), (new ReflectionClass(AuditLogRepository::class))->getMethods(ReflectionMethod::IS_PUBLIC));
        self::assertSame([], array_values(array_filter($methods, static fn (string $name): bool => preg_match('/update|delete|remove|truncate/i', $name) === 1)));

        (new AuditLogRepository())->append(['request_id' => str_repeat('a', 16), 'actor_id' => null, 'actor_type' => 'user', 'action' => 'auth.login', 'target_type' => null, 'target_id' => null, 'outcome' => 'success', 'reason' => null, 'changed_fields' => null, 'occurred_at' => '2026-10-11 00:00:00']);
        self::assertSame(1, (new AuditLogRepository())->search(['action' => 'auth.login'], 1)['total']);
    }

    public function testBrandAssetsAreKeptPerTemplateAndRemovedWithTheProfile(): void
    {
        [$userId, $profileId] = $this->makeAccount('merek');
        [, $otherProfile] = $this->makeAccount('lain');
        $repo = new App\Repositories\BrandAssetRepository();

        $repo->save($profileId, 'layout-pro-aurora', ['brand_logo' => 'a.webp', 'header_background' => 'b.webp'], '2026-10-12 10:00:00');
        $repo->save($profileId, 'layout-pro-rona', ['brand_logo' => 'r.webp'], '2026-10-12 10:00:00');
        // Updating one field leaves the other untouched; clearing writes NULL.
        $repo->save($profileId, 'layout-pro-aurora', ['brand_logo' => 'a2.webp'], '2026-10-12 11:00:00');
        $repo->save($profileId, 'layout-pro-rona', ['brand_logo' => null], '2026-10-12 11:00:00');

        $repo->save($profileId, 'layout-pro-aurora', ['brand_logo_visible' => false], '2026-10-12 12:00:00');

        self::assertSame(['brand_logo' => 'a2.webp', 'header_background' => 'b.webp', 'brand_logo_visible' => false, 'header_background_visible' => true], $repo->find($profileId, 'layout-pro-aurora'));
        self::assertSame(['brand_logo' => null, 'header_background' => null, 'brand_logo_visible' => true, 'header_background_visible' => true], $repo->find($profileId, 'layout-pro-rona'));
        self::assertNull($repo->find($otherProfile, 'layout-pro-aurora'), 'Another profile sees nothing.');

        $this->db->table('table_user')->where('id_user', $userId)->delete();
        self::assertSame(0, $this->db->table('card_brand_assets')->where('profile_id', $profileId)->countAllResults());
    }

    public function testJobTitleIsStoredWithTheProfile(): void
    {
        [$userId] = $this->makeAccount('jabatan');
        (new App\Repositories\ProfileRepository())->saveForUser($userId, ['job_title' => 'Direktur']);
        self::assertSame('Direktur', $this->db->table('table_data_personal')->where('id_user', $userId)->get()->getRow()->job_title);
    }

    private function paymentOrder(int $userId, string $code, int $amount = 199000, string $status = 'pending', string $plan = 'pro'): int
    {
        $planId = (int) $this->db->table('plans')->where('code', $plan)->get()->getRow()->id;
        $repo = new App\Repositories\PaymentOrderRepository();

        return $repo->insertOrder([
            'order_code' => $code, 'user_id' => $userId, 'plan_id' => $planId, 'plan_code' => $plan, 'plan_version' => 1,
            'duration_months' => 12, 'amount_idr' => $amount, 'currency' => 'IDR', 'status' => $status,
            'request_key' => bin2hex(random_bytes(16)), 'expires_at' => gmdate('Y-m-d H:i:s', time() + 86400),
            'created_at' => gmdate('Y-m-d H:i:s'), 'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    private function settlement(string $code, string $amount = '199000.00', string $status = 'settlement', string $txn = 'txn-1'): array
    {
        return ['order_id' => $code, 'transaction_status' => $status, 'status_code' => $status === 'settlement' ? '200' : '201',
            'gross_amount' => $amount, 'currency' => 'IDR', 'payment_type' => 'bank_transfer', 'transaction_id' => $txn,
            'settlement_time' => '2026-10-13 10:00:00'];
    }

    public function testVerifiedPaymentAddsExactlyOnePeriodEvenWhenRepeated(): void
    {
        [$userId] = $this->makeAccount('pembeli');
        $this->paymentOrder($userId, 'NFC-T-1');
        $service = new App\Services\Payments\ApplyPaymentService();
        $now = new DateTimeImmutable('2026-10-13 03:00:00', new DateTimeZone('UTC'));

        self::assertSame('applied', $service->apply($this->settlement('NFC-T-1'), 'notification', $now));
        // Same notification again, then the status check reporting the same thing: no second period.
        self::assertSame('duplicate', $service->apply($this->settlement('NFC-T-1'), 'notification', $now));
        self::assertContains($service->apply($this->settlement('NFC-T-1'), 'status', $now), ['duplicate', 'ignored']);
        // A late "pending" never downgrades a paid order.
        $service->apply($this->settlement('NFC-T-1', '199000.00', 'pending', 'txn-1'), 'notification', $now);

        $order = $this->db->table('payment_orders')->where('order_code', 'NFC-T-1')->get()->getRowArray();
        self::assertSame('paid', $order['status']);
        self::assertNotNull($order['applied_at']);
        self::assertSame(1, $this->db->table('subscriptions')->where('user_id', $userId)->where('source', 'payment')->countAllResults());
        $sub = $this->db->table('subscriptions')->where('id', $order['subscription_id'])->get()->getRowArray();
        // 12 months from the WIB day of payment: 13 Oct 2026 → covers through 13 Oct 2027 WIB.
        self::assertSame(['2026-10-13 03:00:00', '2027-10-13 17:00:00'], [$sub['starts_at'], $sub['ends_at']]);
        self::assertSame('pro', $this->db->table('plans')->where('id', $sub['plan_id'])->get()->getRow()->code);
        self::assertSame(1, $this->db->table('audit_events')->where('action', 'payment.applied')->countAllResults());
        self::assertSame('payment', $this->db->table('subscription_grants')->where('subscription_id', $sub['id'])->get()->getRow()->kind);
    }

    public function testRenewalStartsAfterTheCurrentPeriodEnds(): void
    {
        [$userId] = $this->makeAccount('perpanjang');
        (new App\Services\Cards\SubscriptionGrantService())->grant($userId, 1, '+1 month', new DateTimeImmutable('2026-10-01 03:00:00', new DateTimeZone('UTC')), 'awal uji');
        $this->paymentOrder($userId, 'NFC-T-2');
        (new App\Services\Payments\ApplyPaymentService())->apply($this->settlement('NFC-T-2'), 'notification', new DateTimeImmutable('2026-10-13 03:00:00', new DateTimeZone('UTC')));

        $paid = $this->db->table('subscriptions')->where('user_id', $userId)->where('source', 'payment')->get()->getRowArray();
        $manual = $this->db->table('subscriptions')->where('user_id', $userId)->where('source', 'manual')->get()->getRowArray();
        self::assertSame($manual['ends_at'], $paid['starts_at'], 'The paid period begins exactly when the current one ends.');
    }

    public function testWrongAmountOrSuspendedAccountIsHeldForReviewWithoutAccess(): void
    {
        [$userId] = $this->makeAccount('salahjumlah');
        $this->paymentOrder($userId, 'NFC-T-3');
        $service = new App\Services\Payments\ApplyPaymentService();
        $now = new DateTimeImmutable('2026-10-13 03:00:00', new DateTimeZone('UTC'));
        self::assertSame('review', $service->apply($this->settlement('NFC-T-3', '1000.00'), 'notification', $now));
        $order = $this->db->table('payment_orders')->where('order_code', 'NFC-T-3')->get()->getRowArray();
        self::assertSame(['pending', '1'], [$order['status'], (string) $order['needs_review']]);

        [$suspended] = $this->makeAccount('ditangguhkan');
        $this->db->table('table_user')->where('id_user', $suspended)->update(['account_status' => 'suspended']);
        $this->paymentOrder($suspended, 'NFC-T-4');
        self::assertSame('review', $service->apply($this->settlement('NFC-T-4'), 'notification', $now));
        $order = $this->db->table('payment_orders')->where('order_code', 'NFC-T-4')->get()->getRowArray();
        self::assertSame(['paid', '1'], [$order['status'], (string) $order['needs_review']]);

        self::assertSame(0, $this->db->table('subscriptions')->whereIn('user_id', [$userId, $suspended])->countAllResults(), 'Neither case grants access.');
        self::assertSame('unknown_order', $service->apply($this->settlement('NFC-TIDAK-ADA'), 'notification', $now));
    }

    public function testFailedAuditRollsBackThePaymentPeriod(): void
    {
        [$userId] = $this->makeAccount('auditgagal');
        $this->paymentOrder($userId, 'NFC-T-5');
        $this->db->query("CREATE TRIGGER audit_block_pay BEFORE INSERT ON audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit down'");
        try {
            (new App\Services\Payments\ApplyPaymentService())->apply($this->settlement('NFC-T-5'), 'notification', new DateTimeImmutable('2026-10-13 03:00:00', new DateTimeZone('UTC')));
            self::fail('The audit failure must surface.');
        } catch (Throwable) {
        } finally {
            $this->db->query('DROP TRIGGER IF EXISTS audit_block_pay');
        }
        $order = $this->db->table('payment_orders')->where('order_code', 'NFC-T-5')->get()->getRowArray();
        self::assertSame(['pending', null], [$order['status'], $order['applied_at']], 'Nothing is kept, so Midtrans retries.');
        self::assertSame(0, $this->db->table('subscriptions')->where('user_id', $userId)->countAllResults());
    }

    public function testOwnerOnlySeesOwnOrdersAndRequestKeyIsUniquePerUser(): void
    {
        [$a] = $this->makeAccount('pemilika');
        [$b] = $this->makeAccount('pemilikb');
        $this->paymentOrder($a, 'NFC-T-6');
        $repo = new App\Repositories\PaymentOrderRepository();
        self::assertNotNull($repo->findOwnedOrder('NFC-T-6', $a));
        self::assertNull($repo->findOwnedOrder('NFC-T-6', $b));

        $key = $this->db->table('payment_orders')->where('order_code', 'NFC-T-6')->get()->getRow()->request_key;
        $this->expectException(Throwable::class);
        $this->db->table('payment_orders')->insert(['order_code' => 'NFC-T-7', 'user_id' => $a, 'plan_id' => 1, 'plan_code' => 'basic', 'plan_version' => 1, 'duration_months' => 12,
            'amount_idr' => 1, 'status' => 'pending', 'request_key' => $key, 'expires_at' => gmdate('Y-m-d H:i:s'), 'created_at' => gmdate('Y-m-d H:i:s'), 'updated_at' => gmdate('Y-m-d H:i:s')]);
    }

    public function testBillingHistoryKeepsRefundedOrdersAndWaitingIsSeparate(): void
    {
        [$userId] = $this->makeAccount('riwayat');
        [$other] = $this->makeAccount('lainnya');
        $this->paymentOrder($userId, 'NFC-H-PAID', 99000, 'paid', 'basic');
        $this->db->table('payment_orders')->where('order_code', 'NFC-H-PAID')->update(['paid_at' => '2026-10-13 01:00:00']);
        $this->paymentOrder($userId, 'NFC-H-WAIT', 199000, 'pending');
        $this->paymentOrder($userId, 'NFC-H-FAIL', 99000, 'failed', 'basic');
        $this->paymentOrder($userId, 'NFC-H-OLD', 99000, 'pending', 'basic');
        $this->db->table('payment_orders')->where('order_code', 'NFC-H-OLD')->update(['expires_at' => '2026-01-01 00:00:00']);
        $this->paymentOrder($other, 'NFC-H-OTHER', 99000, 'paid', 'basic');
        // A refunded order stays in the history (its receipt link in the email must keep working).
        $this->paymentOrder($userId, 'NFC-H-REFUND', 99000, 'refunded', 'basic');
        $this->db->table('payment_orders')->where('order_code', 'NFC-H-REFUND')->update(['paid_at' => '2026-10-12 01:00:00']);

        $repo = new App\Repositories\PaymentOrderRepository();
        self::assertSame(['NFC-H-PAID', 'NFC-H-REFUND'], array_column($repo->paidForUser($userId), 'order_code'));
        self::assertSame(['NFC-H-WAIT'], array_column($repo->waitingForUser($userId, gmdate('Y-m-d H:i:s')), 'order_code'));
    }

    public function testSuperAdminResolvesReviewsWithAuditAndExactlyOnce(): void
    {
        [$userId] = $this->makeAccount('tinjauan');
        $this->db->table('table_user')->where('id_user', $userId)->update(['account_status' => 'suspended']);
        $this->paymentOrder($userId, 'NFC-R-1');
        $service = new App\Services\Payments\ApplyPaymentService();
        $now = new DateTimeImmutable('2026-10-26 03:00:00', new DateTimeZone('UTC'));
        self::assertSame('review', $service->apply($this->settlement('NFC-R-1'), 'notification', $now));

        // Account restored, then the review grants the paid period once, with the admin recorded.
        $this->db->table('table_user')->where('id_user', $userId)->update(['account_status' => 'enabled']);
        $service->resolve('NFC-R-1', 'grant', 'Akun sudah dipulihkan', 1, $now);
        $order = $this->db->table('payment_orders')->where('order_code', 'NFC-R-1')->get()->getRowArray();
        self::assertSame(['0', 'grant', '1'], [(string) $order['needs_review'], $order['review_action'], (string) $order['reviewed_by']]);
        self::assertNotNull($order['subscription_id']);
        self::assertSame(1, $this->db->table('subscriptions')->where('user_id', $userId)->where('source', 'payment')->countAllResults());
        self::assertSame('1', (string) $this->db->table('subscription_grants')->where('subscription_id', $order['subscription_id'])->get()->getRow()->actor_id);
        self::assertSame(1, $this->db->table('audit_events')->where('action', 'payment.resolved')->where('target_id', 'NFC-R-1')->countAllResults());

        // Full refund later: the period is revoked automatically, with its audit; history kept.
        self::assertSame('refunded', $service->apply($this->settlement('NFC-R-1', '199000.00', 'refund', 'txn-refund'), 'notification', $now));
        $sub = $this->db->table('subscriptions')->where('id', $order['subscription_id'])->get()->getRowArray();
        self::assertNotNull($sub['revoked_at']);
        self::assertNull((new App\Repositories\SubscriptionRepository())->currentForUser($userId, '2026-10-26 03:00:01'));
        self::assertSame(1, $this->db->table('audit_events')->where('action', 'payment.refunded')->where('target_id', 'NFC-R-1')->countAllResults());
        self::assertSame('0', (string) $this->db->table('payment_orders')->where('order_code', 'NFC-R-1')->get()->getRow()->needs_review);
    }

    public function testRenewalRemindersAndReceiptsAreClaimedOnce(): void
    {
        [$userId] = $this->makeAccount('pengingat');
        $subs = new App\Repositories\SubscriptionRepository();
        // Last active WIB day 2 Nov 2026 -> ends_at = 3 Nov 00:00 WIB = 2 Nov 17:00 UTC.
        $this->db->table('subscriptions')->insert(['user_id' => $userId, 'plan_id' => 1, 'starts_at' => '2026-10-01 00:00:00', 'ends_at' => '2026-11-02 17:00:00', 'source' => 'manual', 'created_at' => '2026-10-01 00:00:00']);
        $subId = (int) $this->db->insertID();

        $due = $subs->endingBetween('2026-11-02 17:00:00', '2026-11-03 17:00:00', 'h7', 10);
        self::assertSame([$subId], array_map('intval', array_column($due, 'id')));
        self::assertTrue($subs->claimReminder($subId, 'h7', '2026-10-26 03:00:00'));
        self::assertFalse($subs->claimReminder($subId, 'h7', '2026-10-26 04:00:00'), 'An overlapping run cannot send it twice.');
        self::assertSame([], $subs->endingBetween('2026-11-02 17:00:00', '2026-11-03 17:00:00', 'h7', 10));

        // Already renewed (a later period exists): no reminder for the old period.
        $this->db->table('subscriptions')->insert(['user_id' => $userId, 'plan_id' => 1, 'starts_at' => '2026-11-02 17:00:00', 'ends_at' => '2027-11-02 17:00:00', 'source' => 'payment', 'created_at' => '2026-10-26 00:00:00']);
        self::assertSame([], $subs->endingBetween('2026-11-02 17:00:00', '2026-11-03 17:00:00', 'h1', 10));

        $this->paymentOrder($userId, 'NFC-RC-1', 99000, 'paid', 'basic');
        $this->db->table('payment_orders')->where('order_code', 'NFC-RC-1')->update(['paid_at' => gmdate('Y-m-d H:i:s'), 'applied_at' => gmdate('Y-m-d H:i:s')]);
        $orders = new App\Repositories\PaymentOrderRepository();
        $receipts = $orders->receiptsToSend(gmdate('Y-m-d H:i:s', time() - 86400), 10);
        self::assertSame(['NFC-RC-1'], array_column($receipts, 'order_code'));
        self::assertTrue($orders->markReceiptSent((int) $receipts[0]['id'], gmdate('Y-m-d H:i:s')));
        self::assertFalse($orders->markReceiptSent((int) $receipts[0]['id'], gmdate('Y-m-d H:i:s')));
        self::assertSame([], $orders->receiptsToSend(gmdate('Y-m-d H:i:s', time() - 86400), 10));
    }

    public function testReconcileBacksOffOrdersWhoseChecksKeepFailing(): void
    {
        [$userId] = $this->makeAccount('backoff');
        $this->paymentOrder($userId, 'NFC-B-OK');
        $this->paymentOrder($userId, 'NFC-B-BAD');
        $checked = gmdate('Y-m-d H:i:s', time() - 600);
        $this->db->table('payment_orders')->whereIn('order_code', ['NFC-B-OK', 'NFC-B-BAD'])->update(['last_checked_at' => $checked]);
        // Five failures: next check after 5 min x 32 = 160 min, so 10 minutes later it is skipped.
        $this->db->table('payment_orders')->where('order_code', 'NFC-B-BAD')->update(['check_failures' => 5]);

        $codes = array_column((new App\Repositories\PaymentOrderRepository())->needingReconcile(gmdate('Y-m-d H:i:s'), 50), 'order_code');
        self::assertContains('NFC-B-OK', $codes);
        self::assertNotContains('NFC-B-BAD', $codes);
    }

    public function testCancellingAnInvoiceThatWasPaidMeanwhileAppliesItInstead(): void
    {
        [$userId] = $this->makeAccount('batalbayar');
        $this->paymentOrder($userId, 'NFC-CANCEL-PAID', 99000, 'pending', 'basic');
        $config = new Config\Midtrans();
        $config->serverKey = 'k';
        $gateway = new class ($config) extends App\Libraries\MidtransClient {
            public function cancel(string $orderCode): string
            {
                return '412';
            }

            public function status(string $orderCode): ?array
            {
                return ['order_id' => $orderCode, 'transaction_status' => 'settlement', 'status_code' => '200', 'gross_amount' => '99000.00',
                    'currency' => 'IDR', 'transaction_id' => 't-c', 'settlement_time' => '2026-10-13 10:00:00'];
            }
        };
        $orders = new App\Repositories\PaymentOrderRepository();
        $result = (new App\Services\Payments\CancelPaymentService($orders, $gateway))->cancel($userId, 'NFC-CANCEL-PAID', new DateTimeImmutable('2026-10-13 03:00:00', new DateTimeZone('UTC')));

        self::assertSame('already_paid', $result);
        self::assertSame('paid', $orders->findByCode('NFC-CANCEL-PAID')['status']);
        self::assertSame(1, $this->db->table('subscriptions')->where('user_id', $userId)->where('source', 'payment')->countAllResults());
    }
}
