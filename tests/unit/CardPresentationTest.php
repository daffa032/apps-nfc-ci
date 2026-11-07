<?php

declare(strict_types=1);

use App\Services\Cards\CardPresentation;
use App\Services\Cards\TemplateCatalog;
use PHPUnit\Framework\TestCase;

final class CardPresentationTest extends TestCase
{
    public function testOnlyKnownTemplatesResolveToViews(): void
    {
        // The two original cards keep their stored names and render on the shared structure.
        self::assertSame('digital_card/classic', TemplateCatalog::viewFor('index-white.php'));
        self::assertSame('digital_card/classic', TemplateCatalog::viewFor('index-dark.php'));
        self::assertSame('classic-white', TemplateCatalog::layoutFor('index-white.php'));
        self::assertSame('classic-dark', TemplateCatalog::layoutFor('index-dark.php'));
        self::assertSame([], TemplateCatalog::brandSlots('index-dark.php'));

        // Descriptions follow the interface language; an uploaded template shows the admin's text.
        \Config\Services::language()->setLocale('en');
        self::assertSame('Wavy navy cover, round portrait, coral icon contacts.', TemplateCatalog::description(['nama_file' => 'layout-pro-ombak', 'description' => 'x']));
        self::assertSame('Teks admin', TemplateCatalog::description(['nama_file' => 'custom-5', 'description' => 'Teks admin']));
        \Config\Services::language()->setLocale('id');
        self::assertSame('Sampul navy bergelombang, potret bundar, kontak ikon koral.', TemplateCatalog::description(['nama_file' => 'layout-pro-ombak', 'description' => 'x']));
        self::assertNull(TemplateCatalog::viewFor('../dashboard/layouts/dashboard-home'));
    }

    public function testLinksAndImageNamesRejectUnsafeValues(): void
    {
        self::assertNull(CardPresentation::webUrl('javascript:alert(1)'));
        self::assertNull(CardPresentation::webUrl("https://example.test\nInjected: value"));
        self::assertSame('https://example.test/path', CardPresentation::webUrl('https://example.test/path'));
        self::assertNull(CardPresentation::imageName('../config.php'));
        self::assertSame('photo.jpg', CardPresentation::imageName('photo.jpg'));
    }

    public function testVcardEscapesProfileLineBreaksAndPunctuation(): void
    {
        $card = CardPresentation::vcard([
            'nama' => "Ada\r\nROLE:ADMIN; Lab",
            'email' => 'ada@example.test',
            'no_whatsapp' => '628123456789',
        ], ['website' => 'https://example.test']);

        self::assertStringContainsString("FN:Ada ROLE:ADMIN\\; Lab\r\n", $card);
        self::assertStringNotContainsString("\r\nROLE:ADMIN", $card);
    }

    public function testVcardCarriesCompanyAndJobTitle(): void
    {
        $card = CardPresentation::vcard(['nama' => 'Ada', 'company_name' => 'PT Uji; Cabang', 'job_title' => "Direktur\nTITLE:X"], null);

        self::assertStringContainsString("ORG:PT Uji\\; Cabang\r\n", $card);
        self::assertStringContainsString("TITLE:Direktur TITLE:X\r\n", $card);
    }
}
