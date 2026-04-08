<?php

namespace App\Database\Seeds;

use App\Models\AccountModel;
use CodeIgniter\Database\Seeder;

class AccountingBootstrapSeeder extends Seeder
{
    public function run()
    {
        $model = new AccountModel();

        foreach ($this->defaultAccounts() as $account) {
            $existing = $model->where('code', $account['code'])->first();

            if ($existing === null) {
                $model->insert($account);
                continue;
            }

            $model->update($existing['id'], [
                'name' => $account['name'],
                'category' => $account['category'],
                'is_active' => $account['is_active'],
            ]);
        }

        $periodBuilder = $this->db->table('fiscal_periods');
        $periodName = date('Y') . '年度';
        $existsPeriod = $periodBuilder->where('name', $periodName)->countAllResults();

        if (! $existsPeriod) {
            $periodBuilder->insert([
                'name' => $periodName,
                'start_date' => date('Y-01-01'),
                'end_date' => date('Y-12-31'),
                'is_closed' => 0,
            ]);
        }
    }

    /**
     * @return list<array{code:string,name:string,category:string,is_active:int}>
     */
    private function defaultAccounts(): array
    {
        return [
            // 資産
            ['code' => '1110', 'name' => '現金', 'category' => 'asset', 'is_active' => 1],
            ['code' => '1120', 'name' => '普通預金', 'category' => 'asset', 'is_active' => 1],
            ['code' => '1130', 'name' => '当座預金', 'category' => 'asset', 'is_active' => 1],
            ['code' => '1140', 'name' => '定期預金', 'category' => 'asset', 'is_active' => 1],
            ['code' => '1150', 'name' => '郵便振替', 'category' => 'asset', 'is_active' => 1],
            ['code' => '1210', 'name' => '未収会費', 'category' => 'asset', 'is_active' => 1],
            ['code' => '1220', 'name' => '未収寄付金', 'category' => 'asset', 'is_active' => 1],
            ['code' => '1230', 'name' => '未収補助金', 'category' => 'asset', 'is_active' => 1],
            ['code' => '1240', 'name' => '未収金', 'category' => 'asset', 'is_active' => 1],
            ['code' => '1250', 'name' => '立替金', 'category' => 'asset', 'is_active' => 1],
            ['code' => '1260', 'name' => '仮払金', 'category' => 'asset', 'is_active' => 1],
            ['code' => '1310', 'name' => '前払費用', 'category' => 'asset', 'is_active' => 1],
            ['code' => '1410', 'name' => '備品', 'category' => 'asset', 'is_active' => 1],
            ['code' => '1420', 'name' => 'ソフトウェア', 'category' => 'asset', 'is_active' => 1],
            ['code' => '1510', 'name' => '敷金保証金', 'category' => 'asset', 'is_active' => 1],

            // 負債
            ['code' => '2110', 'name' => '未払金', 'category' => 'liability', 'is_active' => 1],
            ['code' => '2120', 'name' => '未払費用', 'category' => 'liability', 'is_active' => 1],
            ['code' => '2130', 'name' => '預り金', 'category' => 'liability', 'is_active' => 1],
            ['code' => '2140', 'name' => '前受会費', 'category' => 'liability', 'is_active' => 1],
            ['code' => '2150', 'name' => '前受金', 'category' => 'liability', 'is_active' => 1],
            ['code' => '2160', 'name' => '仮受金', 'category' => 'liability', 'is_active' => 1],
            ['code' => '2170', 'name' => '未払法人税等', 'category' => 'liability', 'is_active' => 1],
            ['code' => '2180', 'name' => '未払消費税等', 'category' => 'liability', 'is_active' => 1],

            // 正味財産
            ['code' => '3110', 'name' => '一般正味財産', 'category' => 'net_asset', 'is_active' => 1],
            ['code' => '3120', 'name' => '指定正味財産', 'category' => 'net_asset', 'is_active' => 1],
            ['code' => '3130', 'name' => '次期繰越正味財産', 'category' => 'net_asset', 'is_active' => 1],

            // 収益
            ['code' => '4110', 'name' => '受取会費', 'category' => 'revenue', 'is_active' => 1],
            ['code' => '4120', 'name' => '受取寄付金', 'category' => 'revenue', 'is_active' => 1],
            ['code' => '4130', 'name' => '受取補助金', 'category' => 'revenue', 'is_active' => 1],
            ['code' => '4140', 'name' => '受取助成金', 'category' => 'revenue', 'is_active' => 1],
            ['code' => '4150', 'name' => '受託事業収益', 'category' => 'revenue', 'is_active' => 1],
            ['code' => '4160', 'name' => '自主事業収益', 'category' => 'revenue', 'is_active' => 1],
            ['code' => '4170', 'name' => '参加費収益', 'category' => 'revenue', 'is_active' => 1],
            ['code' => '4180', 'name' => '受取利息', 'category' => 'revenue', 'is_active' => 1],
            ['code' => '4190', 'name' => '雑収益', 'category' => 'revenue', 'is_active' => 1],

            // 費用
            ['code' => '5110', 'name' => '事業費', 'category' => 'expense', 'is_active' => 1],
            ['code' => '5120', 'name' => '管理費', 'category' => 'expense', 'is_active' => 1],
            ['code' => '5130', 'name' => '給料手当', 'category' => 'expense', 'is_active' => 1],
            ['code' => '5140', 'name' => '法定福利費', 'category' => 'expense', 'is_active' => 1],
            ['code' => '5150', 'name' => '福利厚生費', 'category' => 'expense', 'is_active' => 1],
            ['code' => '5160', 'name' => '旅費交通費', 'category' => 'expense', 'is_active' => 1],
            ['code' => '5170', 'name' => '通信運搬費', 'category' => 'expense', 'is_active' => 1],
            ['code' => '5180', 'name' => '消耗品費', 'category' => 'expense', 'is_active' => 1],
            ['code' => '5190', 'name' => '水道光熱費', 'category' => 'expense', 'is_active' => 1],
            ['code' => '5210', 'name' => '地代家賃', 'category' => 'expense', 'is_active' => 1],
            ['code' => '5220', 'name' => '支払手数料', 'category' => 'expense', 'is_active' => 1],
            ['code' => '5230', 'name' => '支払報酬料', 'category' => 'expense', 'is_active' => 1],
            ['code' => '5240', 'name' => '会議費', 'category' => 'expense', 'is_active' => 1],
            ['code' => '5250', 'name' => '広報費', 'category' => 'expense', 'is_active' => 1],
            ['code' => '5260', 'name' => '研修費', 'category' => 'expense', 'is_active' => 1],
            ['code' => '5270', 'name' => '減価償却費', 'category' => 'expense', 'is_active' => 1],
            ['code' => '5280', 'name' => '印刷製本費', 'category' => 'expense', 'is_active' => 1],
            ['code' => '5290', 'name' => '保険料', 'category' => 'expense', 'is_active' => 1],
            ['code' => '5310', 'name' => '租税公課', 'category' => 'expense', 'is_active' => 1],
            ['code' => '5320', 'name' => '修繕費', 'category' => 'expense', 'is_active' => 1],
            ['code' => '5330', 'name' => '支払寄付金', 'category' => 'expense', 'is_active' => 1],
            ['code' => '5340', 'name' => '雑費', 'category' => 'expense', 'is_active' => 1],
        ];
    }
}
