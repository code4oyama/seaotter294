<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCashbookEntries extends Migration
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
            'transaction_date' => [
                'type' => 'DATE',
            ],
            'cash_account_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
            ],
            'direction' => [
                'type' => 'VARCHAR',
                'constraint' => 10,
                'comment' => 'receipt or payment',
            ],
            'amount' => [
                'type' => 'BIGINT',
                'unsigned' => true,
            ],
            'description' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
            ],
            'counterpart_account_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'journal_entry_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ],
            'journalized_at' => [
                'type' => 'DATETIME',
                'null' => true,
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
        $this->forge->addKey('cash_account_id');
        $this->forge->addKey('counterpart_account_id');
        $this->forge->addKey('journal_entry_id');
        $this->forge->addForeignKey('fiscal_period_id', 'fiscal_periods', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('cash_account_id', 'accounts', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('counterpart_account_id', 'accounts', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('journal_entry_id', 'journal_entries', 'id', 'SET NULL', 'SET NULL');
        $this->forge->createTable('cashbook_entries');
    }

    public function down()
    {
        $this->forge->dropTable('cashbook_entries');
    }
}
