<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Index for the live payments page's change marker (MAX(updated_at)), so polling stays an index
 * lookup instead of a table scan as orders grow.
 */
class AddPaymentOrdersUpdatedAtIndex extends Migration
{
    public function up()
    {
        $this->db->query('CREATE INDEX payment_orders_updated_at ON ' . $this->db->prefixTable('payment_orders') . ' (updated_at)');
    }

    public function down()
    {
        $this->db->query('DROP INDEX payment_orders_updated_at ON ' . $this->db->prefixTable('payment_orders'));
    }
}
