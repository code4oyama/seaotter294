<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddAiRequestTextToJournalEntries extends Migration
{
    public function up()
    {
        $this->forge->addColumn('journal_entries', [
            'ai_request_text' => [
                'type' => 'TEXT',
                'null' => true,
                'after' => 'description',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('journal_entries', 'ai_request_text');
    }
}
