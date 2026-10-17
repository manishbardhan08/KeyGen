<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class AddProduct extends Migration
{
    public function up()
    {
        // users.product — each admin's own product
        if (! $this->db->fieldExists('product', 'users')) {
            $this->forge->addColumn('users', [
                'product' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'null'       => true,
                    'after'      => 'phone',
                ],
            ]);
        }

        // keys.product — denormalized for filtering
        if (! $this->db->fieldExists('product', 'keys')) {
            $this->forge->addColumn('keys', [
                'product' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'null'       => true,
                    'after'      => 'duration_days',
                ],
            ]);
        }
    }

    public function down()
    {
        $this->forge->dropColumn('users', 'product');
        $this->forge->dropColumn('keys',  'product');
    }
}
