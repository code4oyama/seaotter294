<?php

namespace App\Models;

use CodeIgniter\Model;

class JournalLineModel extends Model
{
    protected $table = 'journal_lines';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;
    protected $allowedFields = [
        'journal_entry_id',
        'account_id',
        'dc',
        'amount',
        'line_description',
        'sort_order',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected $useTimestamps = true;
}
