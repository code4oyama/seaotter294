<?php

namespace App\Models;

use CodeIgniter\Model;

class FiscalPeriodModel extends Model
{
    protected $table = 'fiscal_periods';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;
    protected $allowedFields = ['name', 'start_date', 'end_date', 'is_closed'];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected array $casts = [
        'is_closed' => 'boolean',
    ];

    protected $useTimestamps = true;
}
