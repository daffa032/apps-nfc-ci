<?php

declare(strict_types=1);

use App\Services\Locale\LocaleResolver;
use Config\Locale;
use PHPUnit\Framework\TestCase;

final class LocaleResolverTest extends TestCase
{
    private LocaleResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new LocaleResolver(new Locale());
    }

    public function testExplicitChoiceWinsOverCountryAndBrowser(): void
    {
        self::assertSame('en', $this->resolver->resolve('en', 'ID', 'id-ID'));
        self::assertSame('id', $this->resolver->resolve('id', 'US', 'en-US'));
    }

    public function testUnsupportedChoiceIsIgnored(): void
    {
        self::assertSame('id', $this->resolver->resolve('fr', null, ''));
        self::assertSame('en', $this->resolver->resolve('<script>', null, 'en-GB'));
    }

    public function testCountryGuessIndonesiaIsIndonesianOthersEnglish(): void
    {
        self::assertSame('id', $this->resolver->resolve(null, 'id', 'en-US'));
        self::assertSame('en', $this->resolver->resolve(null, 'SG', 'id-ID'));
        self::assertSame('en', $this->resolver->resolve(null, 'US', ''));
    }

    public function testUnknownOrInvalidCountryFallsBackToBrowser(): void
    {
        self::assertSame('en', $this->resolver->resolve(null, 'XX', 'en-US'));
        self::assertSame('en', $this->resolver->resolve(null, 'T1', 'en'));
        self::assertSame('id', $this->resolver->resolve(null, 'Indonesia', 'id'));
    }

    public function testAcceptLanguageUsesHighestSupportedWeight(): void
    {
        self::assertSame('en', $this->resolver->resolve(null, null, 'fr-FR,en;q=0.8,id;q=0.5'));
        self::assertSame('id', $this->resolver->resolve(null, null, 'en;q=0.3,id-ID;q=0.9'));
        // "in" is the legacy code for Indonesian.
        self::assertSame('id', $this->resolver->resolve(null, null, 'in-ID'));
    }

    public function testDefaultsToIndonesian(): void
    {
        self::assertSame('id', $this->resolver->resolve(null, null, ''));
        self::assertSame('id', $this->resolver->resolve(null, '', 'fr-FR,de;q=0.9'));
    }

    public function testBothLanguagesHaveTheSameKeys(): void
    {
        foreach (['Card', 'Locale', 'Dashboard', 'Templates', 'Profile', 'Upload', 'Home', 'Analytics', 'Billing', 'Settings', 'Accounts', 'Master', 'Audit', 'Date', 'Auth'] as $file) {
            $id = require APPPATH . "Language/id/{$file}.php";
            $en = require APPPATH . "Language/en/{$file}.php";
            self::assertSame(array_keys($id), array_keys($en), $file);
            self::assertNotContains('', array_map('trim', $id + $en), $file);
        }
    }

    public function testEveryAuditActionHasALabelInBothLanguages(): void
    {
        foreach (['id', 'en'] as $locale) {
            $labels = require APPPATH . "Language/{$locale}/Audit.php";
            foreach (array_keys(\App\Services\Audit\AuditLogService::ACTIONS) as $code) {
                self::assertArrayHasKey('action_' . str_replace('.', '_', $code), $labels, $locale . ' ' . $code);
            }
            foreach (array_keys(\App\Services\Audit\AuditLogService::OUTCOMES) as $code) {
                self::assertArrayHasKey('outcome_' . $code, $labels, $locale . ' ' . $code);
            }
        }
    }

    public function testDatesFollowTheLanguage(): void
    {
        helper('date');
        $language = service('language');
        $before = $language->getLocale();
        $date = new DateTimeImmutable('2026-10-10 05:00:00', new DateTimeZone('UTC'));
        try {
            $language->setLocale('en');
            self::assertSame('10 October 2026', format_date_id($date));
            self::assertSame('1 day', remaining_days_label('2026-10-11', $date));
            self::assertSame('365 days', remaining_days_label('2027-10-10', $date));
            $language->setLocale('id');
            self::assertSame('10 Oktober 2026', format_date_id($date));
            self::assertSame('1 hari', remaining_days_label('2026-10-11', $date));
        } finally {
            $language->setLocale($before);
        }
    }
}
