<?php

declare(strict_types=1);

use App\Controllers\Auth_login;
use PHPUnit\Framework\TestCase;

/**
 * After login the user returns to the dashboard page they asked for (e.g. a link in an email), but a
 * remembered value can never send them to another site or outside the dashboard.
 */
final class LoginRedirectTest extends TestCase
{
    private function target(mixed $value): string
    {
        $method = new ReflectionMethod(Auth_login::class, 'safeTarget');

        return $method->invoke(null, $value);
    }

    public function testDashboardPagesAreKept(): void
    {
        self::assertSame('/dashboard/billing/receipt/NFC-1', $this->target('/dashboard/billing/receipt/NFC-1'));
        self::assertSame('/dashboard/billing?order=NFC-1', $this->target('/dashboard/billing?order=NFC-1'));
        self::assertSame('/dashboard', $this->target('/dashboard'));
    }

    public function testAnythingElseFallsBackToTheDashboard(): void
    {
        foreach ([null, '', 'https://evil.example/dashboard', '//evil.example/dashboard', '/dashboard/../admin', '/login', '/dashboardx', '/dashboard//evil.example', ['x'], 'javascript:alert(1)'] as $bad) {
            self::assertSame('/dashboard', $this->target($bad), var_export($bad, true));
        }
    }
}
