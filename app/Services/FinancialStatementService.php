<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use InvalidArgumentException;

class FinancialStatementService
{
    private readonly BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect(ENVIRONMENT === 'testing' ? 'tests' : null);
    }

    /**
     * @return array{
     *   period: array{id:int,name:string,start_date:string,end_date:string,is_closed:bool},
     *   balanceSheet: array{sections: array{assets:list<array{code:string,name:string,amount:int}>, liabilities:list<array{code:string,name:string,amount:int}>, net_assets:list<array{code:string,name:string,amount:int}>}, totals: array{assets:int,liabilities:int,net_assets:int,liabilities_and_net_assets:int}},
     *   profitLoss: array{sections: array{revenue:list<array{code:string,name:string,amount:int}>, expense:list<array{code:string,name:string,amount:int}>}, totals: array{revenue:int,expense:int,surplus:int}}
     * }
     */
    public function buildForPeriod(int $fiscalPeriodId): array
    {
        $period = $this->db->table('fiscal_periods')->where('id', $fiscalPeriodId)->get()->getRowArray();

        if ($period === null) {
            throw new InvalidArgumentException('会計期間が見つかりません。');
        }

        $journalLinesTable = $this->db->prefixTable('journal_lines');
        $journalEntriesTable = $this->db->prefixTable('journal_entries');
        $accountsTable = $this->db->prefixTable('accounts');

        $sql = <<<SQL
SELECT
    accounts.code AS code,
    accounts.name AS name,
    accounts.category AS category,
    SUM(CASE WHEN journal_lines.dc = 'debit' THEN journal_lines.amount ELSE 0 END) AS debit_total,
    SUM(CASE WHEN journal_lines.dc = 'credit' THEN journal_lines.amount ELSE 0 END) AS credit_total
FROM {$journalLinesTable} AS journal_lines
INNER JOIN {$journalEntriesTable} AS journal_entries ON journal_entries.id = journal_lines.journal_entry_id
INNER JOIN {$accountsTable} AS accounts ON accounts.id = journal_lines.account_id
WHERE journal_entries.fiscal_period_id = ?
GROUP BY accounts.id, accounts.code, accounts.name, accounts.category
ORDER BY accounts.code ASC
SQL;

        $rows = $this->db->query($sql, [$fiscalPeriodId])->getResultArray();

        $balanceSheetSections = [
            'assets' => [],
            'liabilities' => [],
            'net_assets' => [],
        ];
        $profitLossSections = [
            'revenue' => [],
            'expense' => [],
        ];

        $assetTotal = 0;
        $liabilityTotal = 0;
        $netAssetTotal = 0;
        $revenueTotal = 0;
        $expenseTotal = 0;

        foreach ($rows as $row) {
            $debitTotal = (int) ($row['debit_total'] ?? 0);
            $creditTotal = (int) ($row['credit_total'] ?? 0);
            $category = (string) ($row['category'] ?? '');
            $amount = $this->calculateSignedBalance($category, $debitTotal, $creditTotal);

            if ($amount === 0) {
                continue;
            }

            $line = [
                'code' => (string) ($row['code'] ?? ''),
                'name' => (string) ($row['name'] ?? ''),
                'amount' => $amount,
            ];

            switch ($category) {
                case 'asset':
                    $balanceSheetSections['assets'][] = $line;
                    $assetTotal += $amount;
                    break;

                case 'liability':
                    $balanceSheetSections['liabilities'][] = $line;
                    $liabilityTotal += $amount;
                    break;

                case 'net_asset':
                    $balanceSheetSections['net_assets'][] = $line;
                    $netAssetTotal += $amount;
                    break;

                case 'revenue':
                    $profitLossSections['revenue'][] = $line;
                    $revenueTotal += $amount;
                    break;

                case 'expense':
                    $profitLossSections['expense'][] = $line;
                    $expenseTotal += $amount;
                    break;
            }
        }

        $surplus = $revenueTotal - $expenseTotal;
        if ($surplus !== 0) {
            $balanceSheetSections['net_assets'][] = [
                'code' => 'PL',
                'name' => '当期正味財産増減額',
                'amount' => $surplus,
            ];
            $netAssetTotal += $surplus;
        }

        return [
            'period' => [
                'id' => (int) $period['id'],
                'name' => (string) $period['name'],
                'start_date' => (string) $period['start_date'],
                'end_date' => (string) $period['end_date'],
                'is_closed' => (bool) ($period['is_closed'] ?? false),
            ],
            'balanceSheet' => [
                'sections' => $balanceSheetSections,
                'totals' => [
                    'assets' => $assetTotal,
                    'liabilities' => $liabilityTotal,
                    'net_assets' => $netAssetTotal,
                    'liabilities_and_net_assets' => $liabilityTotal + $netAssetTotal,
                ],
            ],
            'profitLoss' => [
                'sections' => $profitLossSections,
                'totals' => [
                    'revenue' => $revenueTotal,
                    'expense' => $expenseTotal,
                    'surplus' => $surplus,
                ],
            ],
        ];
    }

    private function calculateSignedBalance(string $category, int $debitTotal, int $creditTotal): int
    {
        return match ($category) {
            'asset', 'expense' => $debitTotal - $creditTotal,
            'liability', 'net_asset', 'revenue' => $creditTotal - $debitTotal,
            default => 0,
        };
    }
}
