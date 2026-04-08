<?php

namespace App\Controllers;

use App\Libraries\FinancialStatementPdf;
use App\Models\FiscalPeriodModel;
use App\Models\JournalEntryModel;
use App\Services\FinancialStatementService;
use CodeIgniter\Exceptions\PageNotFoundException;
use InvalidArgumentException;

class Reports extends BaseController
{
    public function index()
    {
        $periodModel = new FiscalPeriodModel();
        $periods = $periodModel->orderBy('start_date', 'DESC')->findAll();
        $entryCounts = $this->loadEntryCounts();

        foreach ($periods as &$period) {
            $period['entry_count'] = $entryCounts[(int) $period['id']] ?? 0;
        }
        unset($period);

        return view('reports/index', [
            'title' => '帳票・PDF出力',
            'periods' => $periods,
        ]);
    }

    public function balanceSheetPdf(int $fiscalPeriodId)
    {
        return $this->pdfResponse($fiscalPeriodId, 'balance-sheet');
    }

    public function profitLossPdf(int $fiscalPeriodId)
    {
        return $this->pdfResponse($fiscalPeriodId, 'profit-loss');
    }

    private function pdfResponse(int $fiscalPeriodId, string $statementType)
    {
        $service = new FinancialStatementService();

        try {
            $report = $service->buildForPeriod($fiscalPeriodId);
        } catch (InvalidArgumentException $e) {
            throw PageNotFoundException::forPageNotFound($e->getMessage());
        }

        $pdf = $statementType === 'balance-sheet'
            ? FinancialStatementPdf::createBalanceSheetPdf($report)
            : FinancialStatementPdf::createProfitLossPdf($report);

        $periodLabel = preg_replace('/[^0-9A-Za-z_-]/', '', (string) $report['period']['name']) ?: 'period-' . $fiscalPeriodId;
        $filename = sprintf('%s_%s.pdf', $statementType, $periodLabel);

        return $this->response
            ->setHeader('Content-Disposition', 'inline; filename="' . $filename . '"')
            ->setHeader('Content-Type', 'application/pdf')
            ->setBody($pdf);
    }

    /**
     * @return array<int,int>
     */
    private function loadEntryCounts(): array
    {
        $rows = (new JournalEntryModel())
            ->select('fiscal_period_id, COUNT(*) AS total')
            ->groupBy('fiscal_period_id')
            ->findAll();

        $entryCounts = [];
        foreach ($rows as $row) {
            $entryCounts[(int) $row['fiscal_period_id']] = (int) $row['total'];
        }

        return $entryCounts;
    }
}
