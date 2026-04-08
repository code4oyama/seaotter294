<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateJournalEntries extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'fiscal_period_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
            ],
            'voucher_number' => [
                'type' => 'VARCHAR',
                'constraint' => 40,
            ],
            'entry_date' => [
                'type' => 'DATE',
            ],
            'description' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('fiscal_period_id');
        $this->forge->addUniqueKey('voucher_number');
        $this->forge->addForeignKey('fiscal_period_id', 'fiscal_periods', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('journal_entries');
    }

    public function down()
    {
        $this->forge->dropTable('journal_entries');
    }
}
