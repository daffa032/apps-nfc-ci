<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** Static release-script guards; these tests do not connect to or modify a database. */
final class ProductionUpgradeScriptTest extends TestCase
{
    public function testUpgradeUsesApplicationTablesForFinalVerification(): void
    {
        $sql = $this->readScript('upgrade-production.sql');
        $verification = substr($sql, (int) strpos($sql, '-- Expected: one row'));

        self::assertStringContainsString("SHOW COLUMNS FROM `table_data_personal` WHERE `Field` = 'card_locale';", $verification);
        self::assertStringContainsString('FROM `migrations`', $verification);
        self::assertStringNotContainsString('information_schema', $this->withoutComments($verification));
        self::assertStringNotContainsString('ADD COLUMN IF NOT EXISTS', $sql);
    }

    public function testRecoveryScriptIsReadOnlyAndShowsSelectedDatabase(): void
    {
        $sql = $this->withoutComments($this->readScript('verify-production.sql'));
        $statements = array_values(array_filter(array_map('trim', explode(';', $sql))));

        self::assertCount(3, $statements);
        self::assertSame('SELECT DATABASE() AS current_database', $statements[0]);
        self::assertStringStartsWith('SHOW COLUMNS FROM `table_data_personal`', $statements[1]);
        self::assertStringStartsWith('SELECT `version`, `class`, `group`, `namespace`, `batch`', $statements[2]);
        self::assertStringNotContainsString('information_schema', $sql);
        self::assertDoesNotMatchRegularExpression('/\b(?:ALTER|INSERT|UPDATE|DELETE|DROP|TRUNCATE|CREATE|REPLACE\s+INTO|USE|SET|PREPARE|EXECUTE)\b/i', $sql);
    }

    private function readScript(string $name): string
    {
        $path = dirname(__DIR__, 2) . '/deploy/2026-11-02/' . $name;
        self::assertFileExists($path);
        $sql = file_get_contents($path);
        self::assertIsString($sql);

        return $sql;
    }

    private function withoutComments(string $sql): string
    {
        return (string) preg_replace('/^\s*--[^\r\n]*/m', '', $sql);
    }
}
