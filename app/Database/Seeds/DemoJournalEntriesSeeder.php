<?php

namespace App\Database\Seeds;

use App\Models\AccountModel;
use App\Models\FiscalPeriodModel;
use App\Models\JournalEntryModel;
use App\Models\JournalLineModel;
use App\Services\JournalEntryService;
use CodeIgniter\Database\Seeder;
use RuntimeException;

class DemoJournalEntriesSeeder extends Seeder
{
    public function run()
    {
        $this->call(AccountingBootstrapSeeder::class);
        $this->call(DemoUsersSeeder::class);

        $year = (int) date('Y');
        $definitions = $this->entryDefinitions($year);
        $accountsByCode = $this->loadAccountsByCode($definitions);
        $fiscalPeriodId = $this->ensureFiscalPeriod($year);

        $this->deleteExistingDemoEntries($year);

        $service = new JournalEntryService(new JournalEntryModel(), new JournalLineModel(), $this->db);

        foreach ($definitions as $index => $definition) {
            $service->create([
                'fiscal_period_id' => $fiscalPeriodId,
                'voucher_number' => sprintf('DEMO-%d-%03d', $year, $index + 1),
                'entry_date' => $definition['entry_date'],
                'description' => $definition['description'],
                'ai_request_text' => $definition['ai_request_text'],
            ], [
                [
                    'account_id' => $accountsByCode[$definition['debit_code']],
                    'dc' => 'debit',
                    'amount' => $definition['amount'],
                    'line_description' => $definition['line_description'],
                ],
                [
                    'account_id' => $accountsByCode[$definition['credit_code']],
                    'dc' => 'credit',
                    'amount' => $definition['amount'],
                    'line_description' => $definition['line_description'],
                ],
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
     * @param list<array{entry_date:string,description:string,debit_code:string,credit_code:string,amount:int,line_description:string}> $definitions
     * @return array<string,int>
     */
    private function loadAccountsByCode(array $definitions): array
    {
        $codes = [];
        foreach ($definitions as $definition) {
            $codes[$definition['debit_code']] = true;
            $codes[$definition['credit_code']] = true;
        }

        $accounts = (new AccountModel())
            ->whereIn('code', array_keys($codes))
            ->findAll();

        $accountsByCode = [];
        foreach ($accounts as $account) {
            $accountsByCode[$account['code']] = (int) $account['id'];
        }

        foreach (array_keys($codes) as $code) {
            if (! isset($accountsByCode[$code])) {
                throw new RuntimeException('必要な勘定科目が見つかりません: ' . $code);
            }
        }

        return $accountsByCode;
    }

    private function deleteExistingDemoEntries(int $year): void
    {
        $prefix = sprintf('DEMO-%d-', $year);
        $entryModel = new JournalEntryModel();
        $entryIds = $entryModel->like('voucher_number', $prefix, 'after')->findColumn('id') ?? [];

        if ($entryIds === []) {
            return;
        }

        $this->db->table('journal_lines')->whereIn('journal_entry_id', $entryIds)->delete();
        $this->db->table('journal_entries')->whereIn('id', $entryIds)->delete();
    }

    /**
     * @return list<array{entry_date:string,description:string,debit_code:string,credit_code:string,amount:int,line_description:string,ai_request_text:string}>
     */
    private function entryDefinitions(int $year): array
    {
        $entries = [
            $this->makeEntry(
                sprintf('%d-01-01', $year),
                '[サンプル] 期首残高を普通預金へ設定',
                '1120',
                '3110',
                1800000,
                '前年度からの繰越残高'
            ),
        ];

        for ($month = 1; $month <= 12; $month++) {
            $entries[] = $this->makeEntry(
                sprintf('%d-%02d-05', $year, $month),
                sprintf('[サンプル] %d月分の会費を受領', $month),
                '1120',
                '4110',
                50000 + ($month * 1500),
                sprintf('%d月会費', $month)
            );
            $entries[] = $this->makeEntry(
                sprintf('%d-%02d-20', $year, $month),
                sprintf('[サンプル] %d月分の給与を支給', $month),
                '5130',
                '1120',
                180000 + (($month % 4) * 5000),
                sprintf('%d月給与', $month)
            );
            $entries[] = $this->makeEntry(
                sprintf('%d-%02d-24', $year, $month),
                sprintf('[サンプル] %d月分の事務所家賃を支払', $month),
                '5210',
                '1120',
                85000,
                '事務所家賃'
            );
            $entries[] = $this->makeEntry(
                sprintf('%d-%02d-27', $year, $month),
                sprintf('[サンプル] %d月分の水道光熱費を支払', $month),
                '5190',
                '1120',
                12000 + ($month * 300),
                '電気・ガス・水道料金'
            );
        }

        $entries[] = $this->makeEntry(sprintf('%d-02-10', $year), '[サンプル] 地域企業から寄付金を受領', '1120', '4120', 120000, '地域企業からの寄付');
        $entries[] = $this->makeEntry(sprintf('%d-03-12', $year), '[サンプル] イベント用消耗品を掛け購入', '5180', '2110', 22000, 'イベント備品の購入');
        $entries[] = $this->makeEntry(sprintf('%d-03-25', $year), '[サンプル] 前月の未払金を普通預金から支払', '2110', '1120', 22000, '未払金の精算');
        $entries[] = $this->makeEntry(sprintf('%d-05-20', $year), '[サンプル] 行政から補助金を受領', '1120', '4130', 300000, '活動補助金の入金');
        $entries[] = $this->makeEntry(sprintf('%d-06-18', $year), '[サンプル] セミナー参加費を受領', '1120', '4170', 45000, 'セミナー参加費');
        $entries[] = $this->makeEntry(sprintf('%d-07-08', $year), '[サンプル] 通信回線費を普通預金から支払', '5170', '1120', 9800, 'インターネット回線費');
        $entries[] = $this->makeEntry(sprintf('%d-09-01', $year), '[サンプル] 年間保険料を前払い', '1310', '1120', 60000, 'ボランティア保険料の前払い');
        $entries[] = $this->makeEntry(sprintf('%d-09-30', $year), '[サンプル] 前払保険料の当月分を費用計上', '5290', '1310', 5000, '保険料の月次振替');
        $entries[] = $this->makeEntry(sprintf('%d-10-10', $year), '[サンプル] 募集チラシの印刷費を支払', '5250', '1120', 25000, '広報チラシ印刷費');
        $entries[] = $this->makeEntry(sprintf('%d-11-15', $year), '[サンプル] スタッフ研修費を支払', '5260', '1120', 18000, '会計研修の受講料');
        $entries[] = $this->makeEntry(sprintf('%d-12-20', $year), '[サンプル] 財団から助成金を受領', '1120', '4140', 180000, '年度末助成金の入金');

        usort(
            $entries,
            static fn (array $left, array $right): int => [$left['entry_date'], $left['description']] <=> [$right['entry_date'], $right['description']]
        );

        return $entries;
    }

    /**
     * @return array{entry_date:string,description:string,debit_code:string,credit_code:string,amount:int,line_description:string,ai_request_text:string}
     */
    private function makeEntry(
        string $entryDate,
        string $description,
        string $debitCode,
        string $creditCode,
        int $amount,
        string $lineDescription,
        ?string $aiRequestText = null
    ): array {
        return [
            'entry_date' => $entryDate,
            'description' => $description,
            'debit_code' => $debitCode,
            'credit_code' => $creditCode,
            'amount' => $amount,
            'line_description' => $lineDescription,
            'ai_request_text' => $aiRequestText !== null && trim($aiRequestText) !== ''
                ? $aiRequestText
                : sprintf('AI入力サンプル: %s を複式簿記の仕訳にしてください。', $description),
        ];
    }
}
