<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class FeedbackHelpersTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        helper(['asset', 'feedback']);
    }

    public function testErrorWinsOverSuccessAndBlankIsIgnored(): void
    {
        self::assertSame(['type' => 'error', 'message' => 'Bad'], flash_feedback('Bad', 'Ok'));
        self::assertSame(['type' => 'success', 'message' => 'Ok'], flash_feedback('  ', 'Ok'));
        self::assertNull(flash_feedback(null, ''));
    }

    public function testAssetUrlIsVersionedOnlyWhenFileExists(): void
    {
        $root = sys_get_temp_dir() . '/nfc-asset-' . bin2hex(random_bytes(4));
        mkdir($root . '/assets/shared/css', 0777, true);
        file_put_contents($root . '/assets/shared/css/tokens.css', ':root{}');
        touch($root . '/assets/shared/css/tokens.css', 1700000000);

        try {
            self::assertStringEndsWith('assets/shared/css/tokens.css?v=1700000000', asset_url('/shared/css/tokens.css', $root));
            self::assertStringEndsWith('assets/missing.css', asset_url('missing.css', $root));
        } finally {
            unlink($root . '/assets/shared/css/tokens.css');
            rmdir($root . '/assets/shared/css');
            rmdir($root . '/assets/shared');
            rmdir($root . '/assets');
            rmdir($root);
        }
    }
}
