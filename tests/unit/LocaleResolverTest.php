<?php

declare(strict_types=1);

use App\Services\Audit\AuditLogService;
use App\Services\Locale\LocaleResolver;
use Config\Locale;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class LocaleResolverTest extends TestCase
{
    private LocaleResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new LocaleResolver(new Locale());
    }

    public function testExplicitChoiceWinsOverCountryAndBrowser(): void
    {
        $this->assertSame('en', $this->resolver->resolve('en', 'ID', 'id-ID'));
        $this->assertSame('id', $this->resolver->resolve('id', 'US', 'en-US'));
    }

    public function testUnsupportedChoiceIsIgnored(): void
    {
        $this->assertSame('id', $this->resolver->resolve('fr', null, ''));
        $this->assertSame('en', $this->resolver->resolve('<script>', null, 'en-GB'));
    }

    public function testCountryGuessIndonesiaIsIndonesianOthersEnglish(): void
    {
        $this->assertSame('id', $this->resolver->resolve(null, 'id', 'en-US'));
        $this->assertSame('en', $this->resolver->resolve(null, 'SG', 'id-ID'));
        $this->assertSame('en', $this->resolver->resolve(null, 'US', ''));
    }

    public function testUnknownOrInvalidCountryFallsBackToBrowser(): void
    {
        $this->assertSame('en', $this->resolver->resolve(null, 'XX', 'en-US'));
        $this->assertSame('en', $this->resolver->resolve(null, 'T1', 'en'));
        $this->assertSame('id', $this->resolver->resolve(null, 'Indonesia', 'id'));
    }

    public function testAcceptLanguageUsesHighestSupportedWeight(): void
    {
        $this->assertSame('en', $this->resolver->resolve(null, null, 'fr-FR,en;q=0.8,id;q=0.5'));
        $this->assertSame('id', $this->resolver->resolve(null, null, 'en;q=0.3,id-ID;q=0.9'));
        // "in" is the legacy code for Indonesian.
        $this->assertSame('id', $this->resolver->resolve(null, null, 'in-ID'));
    }

    public function testDefaultsToIndonesian(): void
    {
        $this->assertSame('id', $this->resolver->resolve(null, null, ''));
        $this->assertSame('id', $this->resolver->resolve(null, '', 'fr-FR,de;q=0.9'));
    }

    public function testBothLanguagesHaveTheSameKeys(): void
    {
        foreach (['Card', 'Locale', 'Dashboard', 'Templates', 'Profile', 'Upload', 'Home', 'Analytics', 'Billing', 'Settings', 'Accounts', 'Master', 'Audit', 'Date', 'Auth'] as $file) {
            $id = require APPPATH . "Language/id/{$file}.php";
            $en = require APPPATH . "Language/en/{$file}.php";
            $this->assertSame(array_keys($id), array_keys($en), $file);
            $this->assertNotContains('', array_map('trim', $id + $en), $file);
        }
    }

    public function testEveryAuditActionHasALabelInBothLanguages(): void
    {
        foreach (['id', 'en'] as $locale) {
            $labels = require APPPATH . "Language/{$locale}/Audit.php";

            foreach (array_keys(AuditLogService::ACTIONS) as $code) {
                $this->assertArrayHasKey('action_' . str_replace('.', '_', $code), $labels, $locale . ' ' . $code);
            }

            foreach (array_keys(AuditLogService::OUTCOMES) as $code) {
                $this->assertArrayHasKey('outcome_' . $code, $labels, $locale . ' ' . $code);
            }
        }
    }

    public function testDatesFollowTheLanguage(): void
    {
        helper('date');
        $language = service('language');
        $before   = $language->getLocale();
        $date     = new DateTimeImmutable('2026-10-10 05:00:00', new DateTimeZone('UTC'));

        try {
            $language->setLocale('en');
            $this->assertSame('10 October 2026', format_date_id($date));
            $this->assertSame('1 day', remaining_days_label('2026-10-11', $date));
            $this->assertSame('365 days', remaining_days_label('2027-10-10', $date));
            $language->setLocale('id');
            $this->assertSame('10 Oktober 2026', format_date_id($date));
            $this->assertSame('1 hari', remaining_days_label('2026-10-11', $date));
        } finally {
            $language->setLocale($before);
        }
    }
}
