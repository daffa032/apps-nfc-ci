<?php

declare(strict_types=1);

use App\Services\Locale\CountryLookup;
use App\Services\Locale\LocaleResolver;
use Config\Locale;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class CountryLookupTest extends TestCase
{
    private const DB = __DIR__ . '/../_support/Fixtures/test-country-24.mmdb';

    public function testIndonesianIpGivesIndonesianAndOthersEnglish(): void
    {
        $lookup   = new CountryLookup(self::DB);
        $resolver = new LocaleResolver(new Locale());

        $this->assertSame('ID', $lookup->countryFor('36.70.1.2'));
        $this->assertSame('id', $resolver->resolve(null, $lookup->countryFor('36.70.1.2'), 'en-US'));
        $this->assertSame('en', $resolver->resolve(null, $lookup->countryFor('8.8.8.8'), 'id-ID'));
        // An explicit choice still wins over the IP guess.
        $this->assertSame('en', $resolver->resolve('en', $lookup->countryFor('36.70.1.2'), ''));
    }

    public function testUnknownPrivateOrBrokenInputFallsBackQuietly(): void
    {
        $lookup = new CountryLookup(self::DB);
        $this->assertNull($lookup->countryFor('1.1.1.1'));
        $this->assertNull($lookup->countryFor('192.168.1.10'));
        $this->assertNull($lookup->countryFor('127.0.0.1'));
        $this->assertNull($lookup->countryFor('bukan-ip'));

        $this->assertNull((new CountryLookup('/tidak/ada.mmdb'))->countryFor('36.70.1.2'));
        $this->assertNull((new CountryLookup(__FILE__))->countryFor('36.70.1.2'), 'A broken file must not break the page.');
        $this->assertNull((new CountryLookup(''))->countryFor('36.70.1.2'), 'Empty path turns the lookup off.');

        // Without a country the browser language decides.
        $this->assertSame('en', (new LocaleResolver(new Locale()))->resolve(null, null, 'en-GB'));
    }
}
