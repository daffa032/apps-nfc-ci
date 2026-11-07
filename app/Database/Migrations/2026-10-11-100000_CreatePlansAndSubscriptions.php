<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Subscriptions become the single source of card access (TECHNICAL, "Migrasi dan rollback" step 3).
 *
 * - plans: Basic and Pro. Prices and durations are still a business decision, so price_idr is nullable.
 * - subscriptions: one row per paid or granted period, [starts_at, ends_at) in UTC.
 * - subscription_grants: why a period exists (manual grant by a Super Admin today; payment orders later).
 *
 * Backfill: every User with a valid expired_date gets one Basic period ending at the end of that
 * day in WIB (00:00 WIB the next day, stored as UTC). 0000-00-00 or invalid dates get no period.
 * table_user.expired_date is kept and rewritten from subscriptions for rollback safety.
 */
class CreatePlansAndSubscriptions extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'code' => ['type' => 'VARCHAR', 'constraint' => 32],
            'label' => ['type' => 'VARCHAR', 'constraint' => 64],
            'enabled' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'price_idr' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'duration_months' => ['type' => 'SMALLINT', 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('code');
        $this->forge->createTable('plans', true, ['ENGINE' => 'InnoDB']);

        $now = gmdate('Y-m-d H:i:s');
        $this->db->table('plans')->insertBatch([
            ['code' => 'basic', 'label' => 'Basic', 'enabled' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'pro', 'label' => 'Pro', 'enabled' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id' => ['type' => 'INT'],
            'plan_id' => ['type' => 'INT', 'unsigned' => true],
            'starts_at' => ['type' => 'DATETIME'],
            'ends_at' => ['type' => 'DATETIME'],
            'source' => ['type' => 'VARCHAR', 'constraint' => 16],
            'revoked_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['user_id', 'ends_at']);
        $this->forge->addForeignKey('user_id', 'table_user', 'id_user', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('plan_id', 'plans', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('subscriptions', true, ['ENGINE' => 'InnoDB']);

        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'subscription_id' => ['type' => 'INT', 'unsigned' => true],
            'actor_id' => ['type' => 'INT', 'null' => true],
            'kind' => ['type' => 'VARCHAR', 'constraint' => 16],
            'duration_months' => ['type' => 'SMALLINT', 'unsigned' => true, 'null' => true],
            'note' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('subscription_id');
        $this->forge->addForeignKey('subscription_id', 'subscriptions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('subscription_grants', true, ['ENGINE' => 'InnoDB']);

        $this->backfillFromExpiredDate($now);
    }

    public function down()
    {
        $this->forge->dropTable('subscription_grants', true);
        $this->forge->dropTable('subscriptions', true);
        $this->forge->dropTable('plans', true);
    }

    private function backfillFromExpiredDate(string $now): void
    {
        $basicId = (int) $this->db->table('plans')->select('id')->where('code', 'basic')->get()->getRow()->id;
        $wib = new \DateTimeZone('Asia/Jakarta');
        $utc = new \DateTimeZone('UTC');

        // CAST keeps 0000-00-00 readable under strict SQL modes.
        $users = $this->db->query(
            "SELECT id_user, CAST(expired_date AS CHAR) AS expired_date, CAST(registration_date AS CHAR) AS registration_date
             FROM {$this->db->prefixTable('table_user')} WHERE access_level = ?",
            ['User']
        )->getResultArray();

        foreach ($users as $user) {
            $expiry = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $user['expired_date'], $wib);
            if ($expiry === false || $expiry->format('Y-m-d') !== $user['expired_date']) {
                continue;
            }
            // The legacy table has no period start; use the registration day when it is before the end.
            $registered = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $user['registration_date'], $wib);
            $end = $expiry->modify('+1 day');
            $start = $registered !== false && $registered < $end ? $registered : $expiry;
            $endsAt = $end->setTimezone($utc);
            $startsAt = $start->setTimezone($utc);

            $this->db->table('subscriptions')->insert([
                'user_id' => (int) $user['id_user'],
                'plan_id' => $basicId,
                'starts_at' => $startsAt->format('Y-m-d H:i:s'),
                'ends_at' => $endsAt->format('Y-m-d H:i:s'),
                'source' => 'legacy',
                'created_at' => $now,
            ]);
            $this->db->table('subscription_grants')->insert([
                'subscription_id' => (int) $this->db->insertID(),
                'kind' => 'legacy',
                'note' => 'Imported from table_user.expired_date',
                'created_at' => $now,
            ]);
        }
    }
}
