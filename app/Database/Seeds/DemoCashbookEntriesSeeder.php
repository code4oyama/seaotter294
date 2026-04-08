<?php

namespace App\Database\Seeds;

use App\Models\AccountModel;
use App\Models\CashbookEntryModel;
use App\Models\FiscalPeriodModel;
use App\Services\CashbookService;
use CodeIgniter\Database\Seeder;
use InvalidArgumentException;
use RuntimeException;

class DemoCashbookEntriesSeeder extends Seeder
{
    public function run()
    {
        $this->call(AccountingBootstrapSeeder::class);
        $this->call(DemoUsersSeeder::class);

        $year = (int) date('Y');
        $fiscalPeriodId = $this->ensureFiscalPeriod($year);
        $accountIds = $this->loadAccountIdsByCode([
            '1110', // 現金
            '1120', // 普通預金
            '4110', // 受取会費
            '4120', // 受取寄付金
            '4130', // 受取補助金
            '4170', // 参加費収益
            '5130', // 給料手当
            '5160', // 旅費交通費
            '5170', // 通信運搬費
            '5180', // 消耗品費
            '5190', // 水道光熱費
            '5210', // 地代家賃
            '5250', // 広報費
            '5260', // 研修費
            '5340', // 雑費
        ]);

        $this->deleteExistingDemoEntries($year);

        $service = CashbookService::make($this->db);

        foreach ($this->entryDefinitions($year) as $entry) {
            $service->create([
                'fiscal_period_id' => $fiscalPeriodId,
                'transaction_date' => $entry['transaction_date'],
                'cash_account_id' => $accountIds[$entry['cash_code']],
                'direction' => $entry['direction'],
                'amount' => $entry['amount'],
                'description' => $entry['description'],
                'counterpart_account_id' => $accountIds[$entry['counterpart_code']],
                'notes' => $entry['notes'],
            ]);
        }
    }

    private function ensureFiscalPeriod(int $year): int
    {
        $periodModel = new FiscalPeriodModel();
        $period = $periodModel->where('name', $year . '年度')->first();

        if ($period !== null) {
            return (int) $period['id'];
        }

        $periodModel->insert([
            'name' => $year . '年度',
            'start_date' => sprintf('%d-01-01', $year),
            'end_date' => sprintf('%d-12-31', $year),
            'is_closed' => 0,
        ]);

        return (int) $periodModel->getInsertID();
    }

    /**
     * @param list<string> $codes
     * @return array<string,int>
     */
    private function loadAccountIdsByCode(array $codes): array
    {
        $accounts = (new AccountModel())
            ->whereIn('code', $codes)
            ->findAll();

        $result = [];
        foreach ($accounts as $account) {
            $result[$account['code']] = (int) $account['id'];
        }

        foreach ($codes as $code) {
            if (! isset($result[$code])) {
                throw new RuntimeException('必要な勘定科目が見つかりません: ' . $code);
            }
        }

        return $result;
    }

    private function deleteExistingDemoEntries(int $year): void
    {
        $prefix = sprintf('[サンプル出納帳 %d]', $year);
        (new CashbookEntryModel())->like('description', $prefix, 'after')->delete();
    }

    /**
     * @return list<array{transaction_date:string,cash_code:string,direction:string,amount:int,description:string,counterpart_code:string,notes:string}>
     */
    private function entryDefinitions(int $year): array
    {
        $entries = [
            [
                'transaction_date' => sprintf('%d-01-01', $year),
                'cash_code' => '1120',
                'direction' => 'receipt',
                'amount' => 850000,
                'description' => sprintf('[サンプル出納帳 %d] 期首残高の受入', $year),
                'counterpart_code' => '4120',
                'notes' => '動作確認用のダミーデータ',
            ],
        ];

        for ($month = 1; $month <= 12; $month++) {
            $entries[] = [
                'transaction_date' => sprintf('%d-%02d-05', $year, $month),
                'cash_code' => '1120',
                'direction' => 'receipt',
                'amount' => 50000 + ($month * 1200),
                'description' => sprintf('[サンプル出納帳 %d] %d月 会費入金', $year, $month),
                'counterpart_code' => '4110',
                'notes' => '毎月会費の入金サンプル',
            ];
            $entries[] = [
                'transaction_date' => sprintf('%d-%02d-12', $year, $month),
                'cash_code' => '1120',
                'direction' => 'payment',
                'amount' => 78000,
                'description' => sprintf('[サンプル出納帳 %d] %d月 事務所家賃支払', $year, $month),
                'counterpart_code' => '5210',
                'notes' => '固定費サンプル',
            ];
            $entries[] = [
                'transaction_date' => sprintf('%d-%02d-20', $year, $month),
                'cash_code' => '1120',
                'direction' => 'payment',
                'amount' => 165000 + (($month % 3) * 4000),
                'description' => sprintf('[サンプル出納帳 %d] %d月 給与支払', $year, $month),
                'counterpart_code' => '5130',
                'notes' => '人件費サンプル',
            ];
            $entries[] = [
                'transaction_date' => sprintf('%d-%02d-25', $year, $month),
                'cash_code' => '1110',
                'direction' => 'payment',
                'amount' => 6000 + ($month * 350),
                'description' => sprintf('[サンプル出納帳 %d] %d月 消耗品購入', $year, $month),
                'counterpart_code' => '5180',
                'notes' => '現金払いのサンプル',
            ];
        }

        $entries[] = [
            'transaction_date' => sprintf('%d-03-10', $year),
            'cash_code' => '1120',
            'direction' => 'receipt',
            'amount' => 240000,
            'description' => sprintf('[サンプル出納帳 %d] 行政補助金入金', $year),
            'counterpart_code' => '4130',
            'notes' => '補助金入金サンプル',
        ];
        $entries[] = [
            'transaction_date' => sprintf('%d-06-08', $year),
            'cash_code' => '1120',
            'direction' => 'receipt',
            'amount' => 46000,
            'description' => sprintf('[サンプル出納帳 %d] イベント参加費入金', $year),
            'counterpart_code' => '4170',
            'notes' => 'イベント収益サンプル',
        ];
        $entries[] = [
            'transaction_date' => sprintf('%d-07-16', $year),
            'cash_code' => '1120',
            'direction' => 'payment',
            'amount' => 9800,
            'description' => sprintf('[サンプル出納帳 %d] 通信費支払', $year),
            'counterpart_code' => '5170',
            'notes' => '通信費サンプル',
        ];
        $entries[] = [
            'transaction_date' => sprintf('%d-09-02', $year),
            'cash_code' => '1120',
            'direction' => 'payment',
            'amount' => 13200,
            'description' => sprintf('[サンプル出納帳 %d] 水道光熱費支払', $year),
            'counterpart_code' => '5190',
            'notes' => '水道光熱費サンプル',
        ];
        $entries[] = [
            'transaction_date' => sprintf('%d-10-03', $year),
            'cash_code' => '1120',
            'direction' => 'payment',
            'amount' => 22500,
            'description' => sprintf('[サンプル出納帳 %d] 広報費支払', $year),
            'counterpart_code' => '5250',
            'notes' => '広報費サンプル',
        ];
        $entries[] = [
            'transaction_date' => sprintf('%d-11-11', $year),
            'cash_code' => '1110',
            'direction' => 'payment',
            'amount' => 18000,
            'description' => sprintf('[サンプル出納帳 %d] 研修費支払', $year),
            'counterpart_code' => '5260',
            'notes' => '研修費サンプル',
        ];
        $entries[] = [
            'transaction_date' => sprintf('%d-12-18', $year),
            'cash_code' => '1110',
            'direction' => 'payment',
            'amount' => 7500,
            'description' => sprintf('[サンプル出納帳 %d] 雑費支払', $year),
            'counterpart_code' => '5340',
            'notes' => '雑費サンプル',
        ];

        usort(
            $entries,
            static fn (array $left, array $right): int => [$left['transaction_date'], $left['description']] <=> [$right['transaction_date'], $right['description']]
        );

        return $entries;
    }
}
