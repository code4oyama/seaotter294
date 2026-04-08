<?php

namespace App\Controllers;

use App\Models\AccountModel;
use App\Models\CashbookEntryModel;
use App\Models\FiscalPeriodModel;
use App\Models\JournalEntryModel;

class Dashboard extends BaseController
{
    public function index()
    {
        $accountModel = new AccountModel();
        $cashbookModel = new CashbookEntryModel();
        $periodModel = new FiscalPeriodModel();
        $entryModel = new JournalEntryModel();

        $data = [
            'title' => 'NPO会計ダッシュボード',
            'accountCount' => $accountModel->countAllResults(),
            'cashbookCount' => $cashbookModel->countAllResults(),
            'periodCount' => $periodModel->countAllResults(),
            'entryCount' => $entryModel->countAllResults(),
        ];

        return view('dashboard/index', $data);
    }
}
