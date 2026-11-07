<?php

declare(strict_types=1);

namespace Tests\Support;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use RuntimeException;

/**
 * Repository integration tests on a real MySQL schema (PRD ARC-05).
 *
 * Uses its own database (NFC_TEST_DB, default nfc_repo_test) and refuses any name without "test",
 * so it can never touch the application database. Once per run: drop + create the database, load
 * the legacy base tables (tests/_support/Database/Schema/legacy.sql, structure only) and run every
 * app migration. Before each test all tables are emptied, so fixtures are synthetic and local.
 *
 * Connection: NFC_TEST_DB_HOST/USER/PASS environment variables (defaults: localhost, root, empty
 * password); a host starting with "/" is a socket path, as in CI4. Skipped when MySQL is not reachable.
 */
abstract class RepositoryTestCase extends CIUnitTestCase
{
    private static bool $built = false;

    /** @var BaseConnection connection to the test database (untyped: CIUnitTestCase declares it) */
    protected $db;

    protected function setUp(): void
    {
        parent::setUp();
        $name = getenv('NFC_TEST_DB') ?: 'nfc_repo_test';
        if (!str_contains($name, 'test')) {
            throw new RuntimeException('Refusing to run repository tests on a database without "test" in its name.');
        }

        $config = [
            'DSN' => '', 'hostname' => getenv('NFC_TEST_DB_HOST') ?: 'localhost',
            'username' => getenv('NFC_TEST_DB_USER') ?: 'root', 'password' => getenv('NFC_TEST_DB_PASS') ?: '',
            'database' => $name, 'DBDriver' => 'MySQLi', 'DBPrefix' => '', 'pConnect' => false, 'DBDebug' => true,
            'charset' => 'utf8mb4', 'DBCollat' => 'utf8mb4_general_ci', 'strictOn' => true, 'port' => 3306,
        ];
        $isSocket = str_starts_with($config['hostname'], '/');

        if (!self::$built) {
            $server = @new \mysqli($isSocket ? null : $config['hostname'], $config['username'], $config['password'], '', 3306, $isSocket ? $config['hostname'] : null);
            if ($server->connect_errno) {
                $this->markTestSkipped('MySQL not reachable for repository tests: ' . $server->connect_error);
            }
            $server->query("DROP DATABASE IF EXISTS `{$name}`");
            $server->query("CREATE DATABASE `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
            $server->select_db($name);
            $server->multi_query((string) file_get_contents(__DIR__ . '/Database/Schema/legacy.sql'));
            while ($server->more_results() && $server->next_result()) {
            }
            $server->close();
        }

        // Every repository and model in this process uses this connection, whichever group they ask
        // for. Shared instances left by other test classes (e.g. the SQLite example) are dropped.
        $dbConfig = config(Database::class);
        $dbConfig->default = array_merge($dbConfig->default, $config);
        $dbConfig->tests = $dbConfig->default;
        (new \ReflectionProperty(Database::class, 'instances'))->setValue(null, []);
        $this->db = Database::connect();

        if (!self::$built) {
            $migrations = new \CodeIgniter\Database\MigrationRunner(config('Migrations'), $this->db);
            $migrations->setNamespace('App');
            if (!$migrations->latest('default')) {
                throw new RuntimeException('Migrations failed on the repository test database.');
            }
            self::$built = true;
        }

        // CI4 keeps transStatus false after a failed query inside a transaction until the connection
        // closes; tests share one connection, so start each test with a clean flag.
        (new \ReflectionProperty($this->db, 'transStatus'))->setValue($this->db, true);

        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($this->db->listTables() as $table) {
            if (!in_array($table, ['migrations', 'plans', 'templates'], true)) {
                $this->db->query("TRUNCATE TABLE `{$table}`");
            }
        }
        // Catalog back to the layouts the migrations seed (4 + 9 Pro designs), all inactive Pro.
        $this->db->query("DELETE FROM templates WHERE nama_file NOT IN ('layout-profesional', 'layout-hangat', 'layout-portofolio', 'layout-gelap') AND nama_file NOT LIKE 'layout-pro-%'");
        $this->db->query("UPDATE templates SET is_active = 0, plan_code = 'pro', current_version_id = NULL");
        $this->db->query('SET FOREIGN_KEY_CHECKS = 1');
    }

    /**
     * Synthetic account with a profile and social row. Returns [userId, profileId].
     *
     * @return array{0: int, 1: int}
     */
    protected function makeAccount(string $username, string $role = 'User'): array
    {
        $this->db->table('table_user')->insert([
            'username' => $username, 'password' => password_hash('Rahasia123!', PASSWORD_DEFAULT),
            'email' => $username . '@example.test', 'registration_date' => '2026-01-01',
            'expired_date' => '2027-01-01', 'access_level' => $role,
        ]);
        $userId = (int) $this->db->insertID();
        $this->db->table('table_data_personal')->insert(['id_user' => $userId, 'nama' => 'Nama ' . $username, 'company_name' => 'PT Uji', 'templates' => 'index-white.php']);
        $profileId = (int) $this->db->insertID();
        $this->db->table('sosial_media')->insert(['id_data' => $profileId]);

        return [$userId, $profileId];
    }
}
