<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class FeedbackHelpersTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        helper(['asset', 'feedback']);
    }

    public function testErrorWinsOverSuccessAndBlankIsIgnored(): void
    {
        $this->assertSame(['type' => 'error', 'message' => 'Bad'], flash_feedback('Bad', 'Ok'));
        $this->assertSame(['type' => 'success', 'message' => 'Ok'], flash_feedback('  ', 'Ok'));
        $this->assertNull(flash_feedback(null, ''));
    }

    public function testAssetUrlIsVersionedOnlyWhenFileExists(): void
    {
        $root = sys_get_temp_dir() . '/nfc-asset-' . bin2hex(random_bytes(4));
        mkdir($root . '/assets/shared/css', 0777, true);
        file_put_contents($root . '/assets/shared/css/tokens.css', ':root{}');
        touch($root . '/assets/shared/css/tokens.css', 1700000000);

        try {
            $this->assertStringEndsWith('assets/shared/css/tokens.css?v=1700000000', asset_url('/shared/css/tokens.css', $root));
            $this->assertStringEndsWith('assets/missing.css', asset_url('missing.css', $root));
        } finally {
            unlink($root . '/assets/shared/css/tokens.css');
            rmdir($root . '/assets/shared/css');
            rmdir($root . '/assets/shared');
            rmdir($root . '/assets');
            rmdir($root);
        }
    }
}
