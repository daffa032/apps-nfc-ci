<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class UseTimestampForPasswordResetExpiry extends Migration
{
    public function up()
    {
        // Existing plain tokens cannot be verified by the new hashed-token flow.
        $this->clearResetTokens();

        $this->withZeroDatesAllowed(fn () => $this->forge->modifyColumn('table_user', [
            'reset_expired_at' => [
                'name' => 'reset_expired_at',
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]));
    }

    public function down()
    {
        $this->clearResetTokens();

        $this->withZeroDatesAllowed(fn () => $this->forge->modifyColumn('table_user', [
            'reset_expired_at' => [
                'name' => 'reset_expired_at',
                'type' => 'DATE',
                'null' => true,
            ],
        ]));
    }

    /**
     * Only rows that still hold a token are touched. Accounts that were never activated keep
     * expired_date '0000-00-00', which strict MySQL 8 rejects on any UPDATE of the row; MariaDB's
     * default mode accepts it. Relaxing the zero-date rules for these statements works on both.
     */
    private function clearResetTokens(): void
    {
        $this->withZeroDatesAllowed(fn () => $this->db->table('table_user')
            ->groupStart()->where('reset_token IS NOT NULL')->orWhere('reset_expired_at IS NOT NULL')->groupEnd()
            ->update(['reset_token' => null, 'reset_expired_at' => null]));
    }

    private function withZeroDatesAllowed(callable $work): void
    {
        $this->db->query("SET @nfc_prev_mode = @@SESSION.sql_mode, SESSION sql_mode = REPLACE(REPLACE(@@SESSION.sql_mode, 'NO_ZERO_DATE', ''), 'NO_ZERO_IN_DATE', '')");

        try {
            $work();
        } finally {
            $this->db->query('SET SESSION sql_mode = @nfc_prev_mode');
        }
    }
}
