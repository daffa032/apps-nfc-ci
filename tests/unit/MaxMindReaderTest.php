<?php

declare(strict_types=1);

use App\Libraries\MaxMindReader;
use PHPUnit\Framework\TestCase;

final class MaxMindReaderTest extends TestCase
{
    /**
     * Synthetic MaxMind DB 2.0 fixtures (record size 24 and 28, IPv6 tree with IPv4 under ::/96),
     * built for these tests; they contain no real GeoLite2 data.
     */
    public static function files(): array
    {
        return [[__DIR__ . '/../_support/Fixtures/test-country-24.mmdb'], [__DIR__ . '/../_support/Fixtures/test-country-28.mmdb']];
    }

    /**
     * @dataProvider files
     */
    public function testLooksUpCountries(string $path): void
    {
        $reader = new MaxMindReader($path);
        self::assertSame('ID', $reader->countryCode('36.70.1.2'));
        self::assertSame('ID', $reader->countryCode('103.10.200.1'));
        self::assertSame('US', $reader->countryCode('8.8.8.8'));
        self::assertSame('SG', $reader->countryCode('2001:db8:1::5'));
        // Falls back to the registered country when there is no "country" entry.
        self::assertSame('GB', $reader->countryCode('81.2.69.160'));
        self::assertNull($reader->countryCode('1.1.1.1'));
        self::assertNull($reader->countryCode('::1'));
        self::assertSame('AS', $reader->get('36.70.1.2')['continent']['code']);
    }

    public function testRejectsBadInput(): void
    {
        $reader = new MaxMindReader(self::files()[0][0]);
        $this->expectException(InvalidArgumentException::class);
        $reader->countryCode('not-an-ip');
    }

    public function testRejectsAFileThatIsNotAMaxMindDatabase(): void
    {
        $this->expectException(RuntimeException::class);
        new MaxMindReader(__FILE__);
    }
}
