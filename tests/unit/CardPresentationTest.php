<?php

declare(strict_types=1);

use App\Services\Cards\CardPresentation;
use App\Services\Cards\TemplateCatalog;
use Config\Services;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class CardPresentationTest extends TestCase
{
    public function testOnlyKnownTemplatesResolveToViews(): void
    {
        // The two original cards keep their stored names and render on the shared structure.
        $this->assertSame('digital_card/classic', TemplateCatalog::viewFor('index-white.php'));
        $this->assertSame('digital_card/classic', TemplateCatalog::viewFor('index-dark.php'));
        $this->assertSame('classic-white', TemplateCatalog::layoutFor('index-white.php'));
        $this->assertSame('classic-dark', TemplateCatalog::layoutFor('index-dark.php'));
        $this->assertSame([], TemplateCatalog::brandSlots('index-dark.php'));

        // Descriptions follow the interface language; an uploaded template shows the admin's text.
        Services::language()->setLocale('en');
        $this->assertSame('Wavy navy cover, round portrait, coral icon contacts.', TemplateCatalog::description(['nama_file' => 'layout-pro-ombak', 'description' => 'x']));
        $this->assertSame('Teks admin', TemplateCatalog::description(['nama_file' => 'custom-5', 'description' => 'Teks admin']));
        Services::language()->setLocale('id');
        $this->assertSame('Sampul navy bergelombang, potret bundar, kontak ikon koral.', TemplateCatalog::description(['nama_file' => 'layout-pro-ombak', 'description' => 'x']));
        $this->assertNull(TemplateCatalog::viewFor('../dashboard/layouts/dashboard-home'));
    }

    public function testLinksAndImageNamesRejectUnsafeValues(): void
    {
        $this->assertNull(CardPresentation::webUrl('javascript:alert(1)'));
        $this->assertNull(CardPresentation::webUrl("https://example.test\nInjected: value"));
        $this->assertSame('https://example.test/path', CardPresentation::webUrl('https://example.test/path'));
        $this->assertNull(CardPresentation::imageName('../config.php'));
        $this->assertSame('photo.jpg', CardPresentation::imageName('photo.jpg'));
    }

    public function testVcardEscapesProfileLineBreaksAndPunctuation(): void
    {
        $card = CardPresentation::vcard([
            'nama'        => "Ada\r\nROLE:ADMIN; Lab",
            'email'       => 'ada@example.test',
            'no_whatsapp' => '628123456789',
        ], ['website' => 'https://example.test']);

        $this->assertStringContainsString("FN:Ada ROLE:ADMIN\\; Lab\r\n", $card);
        $this->assertStringNotContainsString("\r\nROLE:ADMIN", $card);
    }

    public function testVcardCarriesCompanyAndJobTitle(): void
    {
        $card = CardPresentation::vcard(['nama' => 'Ada', 'company_name' => 'PT Uji; Cabang', 'job_title' => "Direktur\nTITLE:X"], null);

        $this->assertStringContainsString("ORG:PT Uji\\; Cabang\r\n", $card);
        $this->assertStringContainsString("TITLE:Direktur TITLE:X\r\n", $card);
    }
}
