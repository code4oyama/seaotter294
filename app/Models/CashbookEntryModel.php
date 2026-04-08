<?php

namespace App\Models;

use CodeIgniter\Model;

class CashbookEntryModel extends Model
{
    protected $table = 'cashbook_entries';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;
    protected $allowedFields = [
        'fiscal_period_id',
        'transaction_date',
        'cash_account_id',
        'direction',
        'amount',
        'description',
        'counterpart_account_id',
        'notes',
        'journal_entry_id',
        'journalized_at',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected array $casts = [
        'fiscal_period_id' => 'integer',
        'cash_account_id' => 'integer',
        'counterpart_account_id' => '?integer',
        'amount' => 'integer',
        'journal_entry_id' => '?integer',
    ];

    protected $useTimestamps = true;
}
