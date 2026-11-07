<?php

declare(strict_types=1);

use App\Database\Migrations\AddCardLocaleToProfiles;
use CodeIgniter\Database\MigrationRunner;
use Config\Database;
use Tests\Support\RepositoryTestCase;

require_once __DIR__ . '/../_support/RepositoryTestCase.php';

/**
 * Tests only the guarded synthetic test database, never the production dump or application DB.
 *
 * @internal
 */
final class ProductionUpgradeSqlTest extends RepositoryTestCase
{
    public function testImportAddsCardLocaleAndLedgerOnceWithoutChangingExistingData(): void
    {
        [$owner, $profile] = $this->makeAccount('upgrade-owner');
        $this->db->table('table_user')->where('id_user', $owner)->update(['preferred_locale' => 'en']);
        $beforeUser    = $this->db->table('table_user')->where('id_user', $owner)->get()->getRowArray();
        $beforeProfile = $this->db->table('table_data_personal')->where('id_data', $profile)->get()->getRowArray();
        unset($beforeProfile['card_locale']);

        // Reproduce the production schema before this release using synthetic data only.
        Database::forge($this->db)->dropColumn('table_data_personal', 'card_locale');
        $identity = [
            'version'   => '2026-10-30-090000',
            'class'     => AddCardLocaleToProfiles::class,
            'group'     => 'default',
            'namespace' => 'App',
        ];
        $this->db->table('migrations')->where($identity)->delete();
        $beforeLedger = $this->db->table('migrations')->countAllResults();
        $beforeBatch  = (int) $this->db->table('migrations')->selectMax('batch')->where('group', 'default')->get()->getRow()->batch;
        $sql          = (string) file_get_contents(ROOTPATH . 'deploy/2026-11-02/upgrade-production.sql');

        $this->import($sql);
        $afterProfile = $this->db->table('table_data_personal')->where('id_data', $profile)->get()->getRowArray();
        $this->assertArrayHasKey('card_locale', $afterProfile);
        $this->assertNull($afterProfile['card_locale']);
        unset($afterProfile['card_locale']);
        $this->assertSame($beforeProfile, $afterProfile);
        $this->assertSame($beforeUser, $this->db->table('table_user')->where('id_user', $owner)->get()->getRowArray());
        $this->assertSame($beforeLedger + 1, $this->db->table('migrations')->countAllResults());
        $ledger = $this->db->table('migrations')->where($identity)->get()->getRowArray();
        $this->assertNotNull($ledger);
        $this->assertSame($beforeBatch + 1, (int) $ledger['batch']);

        // Re-import under NO_BACKSLASH_ESCAPES must not duplicate history or reset a chosen locale.
        $this->db->table('table_data_personal')->where('id_data', $profile)->update(['card_locale' => 'en']);
        $mode = (string) $this->db->query('SELECT @@SESSION.sql_mode AS mode')->getRow()->mode;

        try {
            $modes = array_unique(array_filter(array_merge(explode(',', $mode), ['NO_BACKSLASH_ESCAPES'])));
            $this->db->query('SET SESSION sql_mode = ?', [implode(',', $modes)]);
            $this->import($sql);
        } finally {
            $this->db->query('SET SESSION sql_mode = ?', [$mode]);
        }
        $this->assertSame('en', $this->db->table('table_data_personal')->where('id_data', $profile)->get()->getRow()->card_locale);
        $this->assertSame($ledger, $this->db->table('migrations')->where($identity)->get()->getRowArray());
        $this->assertSame($beforeLedger + 1, $this->db->table('migrations')->countAllResults());
        $this->assertSame($beforeUser, $this->db->table('table_user')->where('id_user', $owner)->get()->getRowArray());

        // The SQL ledger must also be recognized by CI4, so a later CLI deploy does not add it twice.
        $runner = new MigrationRunner(config('Migrations'), $this->db);
        $runner->setNamespace('App');
        $this->assertTrue($runner->latest('default'));
        $this->assertSame($beforeLedger + 1, $this->db->table('migrations')->countAllResults());
    }

    private function import(string $sql): void
    {
        $connection = $this->db->connID;
        if (! $connection->multi_query($sql)) {
            throw new RuntimeException('Upgrade SQL import failed: ' . $connection->error);
        }

        while (true) {
            $result = $connection->store_result();
            if ($result instanceof mysqli_result) {
                $result->free();
            }
            if (! $connection->more_results()) {
                break;
            }
            if (! $connection->next_result()) {
                throw new RuntimeException('Upgrade SQL statement failed: ' . $connection->error);
            }
        }
    }
}
