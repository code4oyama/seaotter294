<?php

use App\Database\Seeds\AccountingBootstrapSeeder;
use App\Models\AccountModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * @internal
 */
final class AccountingBootstrapSeederTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace = 'App';
    protected $seed = AccountingBootstrapSeeder::class;

    public function testSeederProvidesNonprofitOrientedAccounts(): void
    {
        $model = new AccountModel();
        $accounts = $model->where('is_active', 1)->orderBy('code', 'ASC')->findAll();

        $this->assertGreaterThanOrEqual(40, count($accounts));
        $this->seeInDatabase('accounts', ['code' => '4120', 'name' => '受取寄付金', 'category' => 'revenue']);
        $this->seeInDatabase('accounts', ['code' => '4130', 'name' => '受取補助金', 'category' => 'revenue']);
        $this->seeInDatabase('accounts', ['code' => '2140', 'name' => '前受会費', 'category' => 'liability']);
        $this->seeInDatabase('accounts', ['code' => '3120', 'name' => '指定正味財産', 'category' => 'net_asset']);
        $this->seeInDatabase('accounts', ['code' => '5190', 'name' => '水道光熱費', 'category' => 'expense']);
    }

    public function testSeederUpdatesExistingAccountsWithoutCreatingDuplicateCodes(): void
    {
        $model = new AccountModel();
        $account = $model->where('code', '3110')->first();

        $this->assertNotNull($account);

        $model->update($account['id'], [
            'name' => '旧正味財産',
            'category' => 'net_asset',
            'is_active' => 0,
        ]);

        $this->seed(AccountingBootstrapSeeder::class);

        $rows = $model->where('code', '3110')->findAll();

        $this->assertCount(1, $rows);
        $this->assertSame('一般正味財産', $rows[0]['name']);
        $this->assertTrue((bool) $rows[0]['is_active']);
    }
}
