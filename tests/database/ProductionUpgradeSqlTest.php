<?php

declare(strict_types=1);

use Tests\Support\RepositoryTestCase;

require_once __DIR__ . '/../_support/RepositoryTestCase.php';

/** Tests only the guarded synthetic test database, never the production dump or application DB. */
final class ProductionUpgradeSqlTest extends RepositoryTestCase
{
    public function testImportAddsCardLocaleAndLedgerOnceWithoutChangingExistingData(): void
    {
        [$owner, $profile] = $this->makeAccount('upgrade-owner');
        $this->db->table('table_user')->where('id_user', $owner)->update(['preferred_locale' => 'en']);
        $beforeUser = $this->db->table('table_user')->where('id_user', $owner)->get()->getRowArray();
        $beforeProfile = $this->db->table('table_data_personal')->where('id_data', $profile)->get()->getRowArray();
        unset($beforeProfile['card_locale']);

        // Reproduce the production schema before this release using synthetic data only.
        Config\Database::forge($this->db)->dropColumn('table_data_personal', 'card_locale');
        $identity = [
            'version' => '2026-10-30-090000',
            'class' => App\Database\Migrations\AddCardLocaleToProfiles::class,
            'group' => 'default',
            'namespace' => 'App',
        ];
        $this->db->table('migrations')->where($identity)->delete();
        $beforeLedger = $this->db->table('migrations')->countAllResults();
        $beforeBatch = (int) $this->db->table('migrations')->selectMax('batch')->where('group', 'default')->get()->getRow()->batch;
        $sql = (string) file_get_contents(ROOTPATH . 'deploy/2026-11-02/upgrade-production.sql');

        $this->import($sql);
        $afterProfile = $this->db->table('table_data_personal')->where('id_data', $profile)->get()->getRowArray();
        self::assertArrayHasKey('card_locale', $afterProfile);
        self::assertNull($afterProfile['card_locale']);
        unset($afterProfile['card_locale']);
        self::assertSame($beforeProfile, $afterProfile);
        self::assertSame($beforeUser, $this->db->table('table_user')->where('id_user', $owner)->get()->getRowArray());
        self::assertSame($beforeLedger + 1, $this->db->table('migrations')->countAllResults());
        $ledger = $this->db->table('migrations')->where($identity)->get()->getRowArray();
        self::assertNotNull($ledger);
        self::assertSame($beforeBatch + 1, (int) $ledger['batch']);

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
        self::assertSame('en', $this->db->table('table_data_personal')->where('id_data', $profile)->get()->getRow()->card_locale);
        self::assertSame($ledger, $this->db->table('migrations')->where($identity)->get()->getRowArray());
        self::assertSame($beforeLedger + 1, $this->db->table('migrations')->countAllResults());
        self::assertSame($beforeUser, $this->db->table('table_user')->where('id_user', $owner)->get()->getRowArray());

        // The SQL ledger must also be recognized by CI4, so a later CLI deploy does not add it twice.
        $runner = new CodeIgniter\Database\MigrationRunner(config('Migrations'), $this->db);
        $runner->setNamespace('App');
        self::assertTrue($runner->latest('default'));
        self::assertSame($beforeLedger + 1, $this->db->table('migrations')->countAllResults());
    }

    private function import(string $sql): void
    {
        $connection = $this->db->connID;
        if (!$connection->multi_query($sql)) {
            throw new RuntimeException('Upgrade SQL import failed: ' . $connection->error);
        }
        do {
            $result = $connection->store_result();
            if ($result instanceof mysqli_result) {
                $result->free();
            }
            if (!$connection->more_results()) {
                break;
            }
            if (!$connection->next_result()) {
                throw new RuntimeException('Upgrade SQL statement failed: ' . $connection->error);
            }
        } while (true);
    }
}
