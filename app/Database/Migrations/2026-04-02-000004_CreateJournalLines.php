<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateJournalLines extends Migration
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
            'journal_entry_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
            ],
            'account_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
            ],
            'dc' => [
                'type' => 'VARCHAR',
                'constraint' => 10,
                'comment' => 'debit or credit',
            ],
            'amount' => [
                'type' => 'BIGINT',
                'unsigned' => true,
            ],
            'line_description' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
            ],
            'sort_order' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0,
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
        $this->forge->addKey('journal_entry_id');
        $this->forge->addKey('account_id');
        $this->forge->addForeignKey('journal_entry_id', 'journal_entries', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('account_id', 'accounts', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('journal_lines');
    }

    public function down()
    {
        $this->forge->dropTable('journal_lines');
    }
}
