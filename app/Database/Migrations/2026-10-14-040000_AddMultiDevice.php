<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Multi-device keys.
 *
 *   keys.device_limit   – how many distinct devices a key may activate on (>=1)
 *   key_devices         – one row per bound device (key_id, hwid)
 *
 * The old single `keys.hwid` column is kept as the "primary" device for
 * backward compatibility, and is back-filled into `key_devices`. Deletions
 * cascade from keys → key_devices.
 */
class AddMultiDevice extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('device_limit', 'keys')) {
            $this->forge->addColumn('keys', [
                'device_limit' => [
                    'type'     => 'INT',
                    'unsigned' => true,
                    'null'     => false,
                    'default'  => 1,
                ],
            ]);
        }

        if (! $this->db->tableExists('key_devices')) {
            $this->forge->addField([
                'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'key_id'     => ['type' => 'INT', 'unsigned' => true],
                'hwid'       => ['type' => 'VARCHAR', 'constraint' => 128],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey('key_id');
            $this->forge->addUniqueKey(['key_id', 'hwid']);
            $this->forge->addForeignKey('key_id', 'keys', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('key_devices', true);
        }

        // Back-fill: register the already-bound HWID of existing used keys.
        $db = \Config\Database::connect();
        if ($this->db->tableExists('key_devices')) {
            $rows = $db->query("SELECT id, hwid FROM `keys` WHERE hwid IS NOT NULL AND hwid != ''")->getResultArray();
            foreach ($rows as $r) {
                $exists = $db->table('key_devices')
                    ->where('key_id', $r['id'])->where('hwid', $r['hwid'])
                    ->countAllResults();
                if (! $exists) {
                    $db->table('key_devices')->insert([
                        'key_id'     => $r['id'],
                        'hwid'       => $r['hwid'],
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }
        }
    }

    public function down()
    {
        if ($this->db->tableExists('key_devices')) {
            $this->forge->dropTable('key_devices', true);
        }
        if ($this->db->fieldExists('device_limit', 'keys')) {
            $this->forge->dropColumn('keys', 'device_limit');
        }
    }
}
