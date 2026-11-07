<?php

declare(strict_types=1);

use App\Libraries\QrCode;
use App\Services\Cards\TemplateSamples;
use App\Traits\RendersCardPages;
use CodeIgniter\HTTP\IncomingRequest;
use Config\Services;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class CardLanguageRenderTest extends TestCase
{
    public function testPublicAndPreviewMarkupUsesCardLanguageAndRestoresDashboardLanguage(): void
    {
        $request  = Services::incomingrequest(null, false);
        $language = Services::language();
        $before   = $language->getLocale();
        $request->setLocale('en');
        $language->setLocale('en');
        $renderer = new class ($request) {
            use RendersCardPages;

            public function __construct(protected IncomingRequest $request)
            {
            }

            public function card(?string $locale): string
            {
                return $this->renderCardPage('digital_card/classic', array_replace(TemplateSamples::card(), [
                    'layout' => 'classic-white', 'cardLocale' => $locale,
                ]));
            }

            public function share(string $locale): string
            {
                return $this->renderCardPage('digital_card/share', [
                    'cardLocale' => $locale, 'username' => 'sample', 'name' => 'Nama Contoh',
                    'jobTitle'   => '', 'company' => '', 'photo' => null,
                    'cardUrl'    => base_url('sample'), 'qr' => QrCode::encode(base_url('sample')),
                ]);
            }
        };

        try {
            foreach ([[null, 'id'], ['id', 'id'], ['en', 'en']] as [$choice, $expected]) {
                $html = $renderer->card($choice);
                $this->assertStringContainsString('<html lang="' . $expected . '">', $html);
                $this->assertStringContainsString(lang('Card.saveContact', [], $expected), $html);
                $this->assertStringNotContainsString('class="nfc-lang', $html);
                $this->assertSame('en', $request->getLocale());
                $this->assertSame('en', $language->getLocale());
            }
            $share = $renderer->share('id');
            $this->assertStringContainsString('<html lang="id">', $share);
            $this->assertStringNotContainsString('class="nfc-lang', $share);
            $this->assertSame('en', $language->getLocale());
        } finally {
            $language->setLocale($before);
        }
    }
}
