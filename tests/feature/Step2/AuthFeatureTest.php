<?php

use App\Database\Seeds\AccountingBootstrapSeeder;
use App\Database\Seeds\DemoUsersSeeder;
use App\Models\AccountModel;
use App\Models\CashbookEntryModel;
use App\Models\FiscalPeriodModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * @internal
 */
final class AuthFeatureTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $refresh = true;
    protected $namespace = 'App';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountingBootstrapSeeder::class);
        $this->seed(DemoUsersSeeder::class);
    }

    public function testLoginPageLoads(): void
    {
        $result = $this->get('/login');

        $result->assertStatus(200);
        $result->assertSee('ログイン');
    }

    public function testUserCanLoginWithSeededViewerAccount(): void
    {
        $result = $this->post('/login', [
            'login' => 'viewer01@example.test',
            'password' => 'password123',
        ]);

        $result->assertStatus(302);
        $result->assertRedirectTo('/');
    }

    public function testViewerCannotCreateCashbookEntry(): void
    {
        $period = (new FiscalPeriodModel())->first();
        $cashAccount = (new AccountModel())->where('code', '1110')->first();

        $this->assertNotNull($period);
        $this->assertNotNull($cashAccount);

        $result = $this->withSession([
            'auth_user' => [
                'id' => 1,
                'name' => '閲覧ユーザー01',
                'role' => 'viewer',
            ],
        ])->post('/cashbook', [
            'fiscal_period_id' => (string) $period['id'],
            'transaction_date' => date('Y-m-d'),
            'cash_account_id' => (string) $cashAccount['id'],
            'direction' => 'receipt',
            'amount' => '1000',
            'description' => '閲覧ユーザーによる作成',
        ]);

        $result->assertStatus(302);
        $this->dontSeeInDatabase('cashbook_entries', [
            'description' => '閲覧ユーザーによる作成',
        ]);
    }

    public function testLimitedEditorCannotDeleteCashbookEntry(): void
    {
        $period = (new FiscalPeriodModel())->first();
        $cashAccount = (new AccountModel())->where('code', '1110')->first();

        $this->assertNotNull($period);
        $this->assertNotNull($cashAccount);

        $model = new CashbookEntryModel();
        $model->insert([
            'fiscal_period_id' => (int) $period['id'],
            'transaction_date' => date('Y-m-d'),
            'cash_account_id' => (int) $cashAccount['id'],
            'direction' => 'payment',
            'amount' => 2000,
            'description' => '削除禁止テスト',
        ]);
        $cashbookId = (int) $model->getInsertID();

        $result = $this->withSession([
            'auth_user' => [
                'id' => 3,
                'name' => '制限付き編集者01',
                'role' => 'editor_limited',
            ],
        ])->post('/cashbook/' . $cashbookId . '/delete');

        $result->assertStatus(302);
        $this->seeInDatabase('cashbook_entries', [
            'id' => $cashbookId,
            'description' => '削除禁止テスト',
        ]);
    }

    public function testAdminCanAccessUserManagementPage(): void
    {
        $result = $this->withSession([
            'auth_user' => [
                'id' => 7,
                'name' => '管理者01',
                'role' => 'admin',
            ],
        ])->get('/users');

        $result->assertStatus(200);
        $result->assertSee('ユーザー管理');
    }
}
