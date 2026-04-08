<?php

use App\Database\Seeds\DemoJournalEntriesSeeder;
use App\Models\AccountModel;
use App\Models\CashbookEntryModel;
use App\Models\FiscalPeriodModel;
use App\Models\JournalEntryModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\CURLRequest;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Database;
use Config\Services;

/**
 * @internal
 */
final class CashbookFeatureTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $refresh = true;
    protected $namespace = 'App';
    protected $seed = DemoJournalEntriesSeeder::class;

    protected function tearDown(): void
    {
        Services::reset(true);
        putenv('openai.apiKey=');
        unset($_ENV['openai.apiKey'], $_SERVER['openai.apiKey']);

        parent::tearDown();
    }

    public function testIndexShowsCashbookPage(): void
    {
        $result = $this->get('/cashbook');

        $result->assertStatus(200);
        $result->assertSee('出納帳');
        $result->assertSee('新規登録');
    }

    public function testIndexShowsPdfOutputLink(): void
    {
        $result = $this->get('/cashbook');

        $result->assertStatus(200);
        $result->assertSee('/cashbook/pdf');
        $result->assertSee('PDF出力');
    }

    public function testIndexUsesResponsiveCardLayoutForMobile(): void
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
            'direction' => 'receipt',
            'amount' => 1500,
            'description' => 'モバイル表示確認用',
        ]);

        $result = $this->get('/cashbook');

        $result->assertStatus(200);
        $this->assertStringContainsString('responsive-table', $result->getBody());
        $this->assertStringContainsString('data-label=', $result->getBody());
    }

    public function testCashbookPdfEndpointReturnsPdfResponse(): void
    {
        $period = (new FiscalPeriodModel())->first();
        $cashAccount = (new AccountModel())->where('code', '1110')->first();

        $this->assertNotNull($period);
        $this->assertNotNull($cashAccount);

        (new CashbookEntryModel())->insert([
            'fiscal_period_id' => (int) $period['id'],
            'transaction_date' => date('Y-m-d'),
            'cash_account_id' => (int) $cashAccount['id'],
            'direction' => 'receipt',
            'amount' => 2500,
            'description' => 'PDF出力確認用の入金',
            'notes' => 'PDFテスト',
        ]);

        $result = $this->get('/cashbook/pdf');

        $result->assertStatus(200);
        $result->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('%PDF-', $result->getBody());
    }

    public function testNewFormShowsCashbookFields(): void
    {
        $result = $this->get('/cashbook/new');

        $result->assertStatus(200);
        $result->assertSee('出納帳を登録');
        $result->assertSee('相手勘定科目');
        $result->assertSee('AIで相手科目候補を提案');
    }

    public function testNewFormCreatesDefaultFiscalPeriodWhenNoneExists(): void
    {
        $db = Database::connect();
        $db->table('journal_lines')->emptyTable();
        $db->table('cashbook_entries')->emptyTable();
        $db->table('journal_entries')->emptyTable();
        $db->table('fiscal_periods')->emptyTable();

        $result = $this->get('/cashbook/new');

        $result->assertStatus(200);
        $this->seeInDatabase('fiscal_periods', [
            'name' => date('Y') . '年度',
            'is_closed' => 0,
        ]);
    }

    public function testCreateRedirectsBackWithErrorWhenPayloadIsInvalid(): void
    {
        $period = (new FiscalPeriodModel())->first();
        $cashAccount = (new AccountModel())->where('code', '1110')->first();

        $this->assertNotNull($period);
        $this->assertNotNull($cashAccount);

        $response = $this->post('/cashbook', [
            'fiscal_period_id' => (string) $period['id'],
            'transaction_date' => ((int) date('Y') - 1) . '-12-31',
            'cash_account_id' => (string) $cashAccount['id'],
            'direction' => 'receipt',
            'amount' => '1200',
            'description' => '期間外の出納帳データ',
        ]);

        $response->assertStatus(302);
        $this->dontSeeInDatabase('cashbook_entries', [
            'description' => '期間外の出納帳データ',
        ]);
    }

    public function testEditAndUpdateWork(): void
    {
        $period = (new FiscalPeriodModel())->first();
        $cashAccount = (new AccountModel())->where('code', '1110')->first();
        $expenseAccount = (new AccountModel())->where('code', '5180')->first();

        $this->assertNotNull($period);
        $this->assertNotNull($cashAccount);
        $this->assertNotNull($expenseAccount);

        $model = new CashbookEntryModel();
        $model->insert([
            'fiscal_period_id' => (int) $period['id'],
            'transaction_date' => date('Y-m-d'),
            'cash_account_id' => (int) $cashAccount['id'],
            'direction' => 'receipt',
            'amount' => 1000,
            'description' => '更新前の出納帳',
        ]);
        $cashbookId = (int) $model->getInsertID();

        $edit = $this->get('/cashbook/' . $cashbookId . '/edit');
        $edit->assertStatus(200);
        $edit->assertSee('出納帳を編集');

        $update = $this->post('/cashbook/' . $cashbookId . '/update', [
            'fiscal_period_id' => (string) $period['id'],
            'transaction_date' => date('Y-m-d'),
            'cash_account_id' => (string) $cashAccount['id'],
            'direction' => 'payment',
            'amount' => '2300',
            'description' => '更新後の出納帳',
            'counterpart_account_id' => (string) $expenseAccount['id'],
            'notes' => '編集テスト',
        ]);

        $update->assertStatus(302);
        $this->seeInDatabase('cashbook_entries', [
            'id' => $cashbookId,
            'direction' => 'payment',
            'amount' => 2300,
            'description' => '更新後の出納帳',
            'counterpart_account_id' => (int) $expenseAccount['id'],
        ]);
    }

    public function testEditThrowsNotFoundWhenEntryIsMissing(): void
    {
        $this->expectException(PageNotFoundException::class);
        $this->expectExceptionMessage('出納帳データが見つかりません。');

        $this->get('/cashbook/999999/edit');
    }

    public function testUpdateRedirectsWithErrorWhenEntryIsAlreadyJournalized(): void
    {
        $period = (new FiscalPeriodModel())->first();
        $cashAccount = (new AccountModel())->where('code', '1120')->first();
        $revenueAccount = (new AccountModel())->where('code', '4110')->first();

        $this->assertNotNull($period);
        $this->assertNotNull($cashAccount);
        $this->assertNotNull($revenueAccount);

        $model = new CashbookEntryModel();
        $model->insert([
            'fiscal_period_id' => (int) $period['id'],
            'transaction_date' => date('Y-m-d'),
            'cash_account_id' => (int) $cashAccount['id'],
            'direction' => 'receipt',
            'amount' => 2400,
            'description' => '仕訳化済み更新不可データ',
            'counterpart_account_id' => (int) $revenueAccount['id'],
        ]);
        $cashbookId = (int) $model->getInsertID();

        $journalize = $this->post('/cashbook/' . $cashbookId . '/journalize');
        $journalize->assertStatus(302);

        $update = $this->post('/cashbook/' . $cashbookId . '/update', [
            'fiscal_period_id' => (string) $period['id'],
            'transaction_date' => date('Y-m-d'),
            'cash_account_id' => (string) $cashAccount['id'],
            'direction' => 'payment',
            'amount' => '9999',
            'description' => '更新されてはいけない摘要',
            'counterpart_account_id' => (string) $revenueAccount['id'],
        ]);

        $update->assertStatus(302);
        $this->dontSeeInDatabase('cashbook_entries', [
            'id' => $cashbookId,
            'description' => '更新されてはいけない摘要',
        ]);
    }

    public function testDeleteRemovesUnjournalizedCashbookEntry(): void
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
            'amount' => 900,
            'description' => '削除対象の出納帳',
        ]);
        $cashbookId = (int) $model->getInsertID();

        $response = $this->post('/cashbook/' . $cashbookId . '/delete');

        $response->assertStatus(302);
        $this->dontSeeInDatabase('cashbook_entries', ['id' => $cashbookId]);
    }

    public function testDeleteRedirectsWithErrorWhenEntryIsAlreadyJournalized(): void
    {
        $period = (new FiscalPeriodModel())->first();
        $cashAccount = (new AccountModel())->where('code', '1120')->first();
        $revenueAccount = (new AccountModel())->where('code', '4110')->first();

        $this->assertNotNull($period);
        $this->assertNotNull($cashAccount);
        $this->assertNotNull($revenueAccount);

        $model = new CashbookEntryModel();
        $model->insert([
            'fiscal_period_id' => (int) $period['id'],
            'transaction_date' => date('Y-m-d'),
            'cash_account_id' => (int) $cashAccount['id'],
            'direction' => 'receipt',
            'amount' => 3100,
            'description' => '仕訳化済み削除不可データ',
            'counterpart_account_id' => (int) $revenueAccount['id'],
        ]);
        $cashbookId = (int) $model->getInsertID();

        $journalize = $this->post('/cashbook/' . $cashbookId . '/journalize');
        $journalize->assertStatus(302);

        $delete = $this->post('/cashbook/' . $cashbookId . '/delete');

        $delete->assertStatus(302);
        $this->seeInDatabase('cashbook_entries', ['id' => $cashbookId]);
    }

    public function testAiSuggestReturnsServerErrorWhenApiKeyIsMissing(): void
    {
        putenv('openai.apiKey=');
        $_ENV['openai.apiKey'] = '';
        $_SERVER['openai.apiKey'] = '';

        $cashAccount = (new AccountModel())->where('code', '1110')->first();
        $this->assertNotNull($cashAccount);

        $result = $this->post('/cashbook/ai-suggest', [
            'transaction_text' => '',
            'transaction_date' => date('Y-m-d'),
            'cash_account_id' => (string) $cashAccount['id'],
            'direction' => 'receipt',
            'amount' => '1800',
            'description' => 'APIキー未設定の確認',
            'notes' => 'AI提案エラー確認',
        ]);

        $result->assertStatus(500);
        $result->assertSee('OpenAI APIキーが未設定');
    }

    public function testAiSuggestReturnsSuggestedCounterpartWhenApiSucceeds(): void
    {
        $this->mockAiResponse([
            'description' => '4月会費の入金',
            'note' => '受取会費として処理',
            'lines' => [
                ['dc' => 'debit', 'account_code' => '1110', 'amount' => 8000, 'line_description' => '4月会費'],
                ['dc' => 'credit', 'account_code' => '4110', 'amount' => 8000, 'line_description' => '4月会費'],
            ],
        ]);

        $cashAccount = (new AccountModel())->where('code', '1110')->first();
        $revenueAccount = (new AccountModel())->where('code', '4110')->first();

        $this->assertNotNull($cashAccount);
        $this->assertNotNull($revenueAccount);

        $result = $this->post('/cashbook/ai-suggest', [
            'transaction_text' => '',
            'transaction_date' => date('Y-m-d'),
            'cash_account_id' => (string) $cashAccount['id'],
            'direction' => 'receipt',
            'amount' => '8000',
            'description' => '4月会費の入金',
            'notes' => 'モック応答',
        ]);

        $result->assertStatus(200);
        $result->assertSee('status');
        $result->assertSee('counterpart_account_id');
        $result->assertSee((string) $revenueAccount['id']);
    }

    public function testAiSuggestUsesDebitCounterpartForPaymentDirection(): void
    {
        $this->mockAiResponse([
            'description' => '消耗品購入',
            'note' => '出金のため借方科目を相手科目として採用',
            'lines' => [
                ['dc' => 'debit', 'account_code' => '5180', 'amount' => 2300, 'line_description' => '消耗品'],
                ['dc' => 'credit', 'account_code' => '1110', 'amount' => 2300, 'line_description' => '現金払い'],
            ],
        ]);

        $cashAccount = (new AccountModel())->where('code', '1110')->first();
        $expenseAccount = (new AccountModel())->where('code', '5180')->first();

        $this->assertNotNull($cashAccount);
        $this->assertNotNull($expenseAccount);

        $result = $this->post('/cashbook/ai-suggest', [
            'transaction_text' => '消耗品を現金で購入した',
            'transaction_date' => date('Y-m-d'),
            'cash_account_id' => (string) $cashAccount['id'],
            'direction' => 'payment',
            'amount' => '2300',
            'description' => '消耗品購入',
        ]);

        $result->assertStatus(200);
        $result->assertSee((string) $expenseAccount['id']);
    }

    public function testAiSuggestFallsBackToFirstNonCashLineWhenDirectionDoesNotMatch(): void
    {
        $this->mockAiResponse([
            'description' => '方向不一致のフォールバック',
            'note' => 'expectedDc と一致しないためフォールバック',
            'lines' => [
                ['dc' => 'debit', 'account_code' => '5180', 'amount' => 1600, 'line_description' => '消耗品'],
                ['dc' => 'credit', 'account_code' => '1110', 'amount' => 1600, 'line_description' => '現金'],
            ],
        ]);

        $cashAccount = (new AccountModel())->where('code', '1110')->first();
        $expenseAccount = (new AccountModel())->where('code', '5180')->first();

        $this->assertNotNull($cashAccount);
        $this->assertNotNull($expenseAccount);

        $result = $this->post('/cashbook/ai-suggest', [
            'transaction_text' => '現金で消耗品を買った',
            'transaction_date' => date('Y-m-d'),
            'cash_account_id' => (string) $cashAccount['id'],
            'direction' => 'receipt',
            'amount' => '1600',
            'description' => '方向不一致のフォールバック',
        ]);

        $result->assertStatus(200);
        $result->assertSee((string) $expenseAccount['id']);
    }

    public function testAiSuggestReturnsZeroCounterpartWhenOnlyCashLinesExist(): void
    {
        $this->mockAiResponse([
            'description' => '同一口座振替',
            'note' => '相手科目が特定できないケース',
            'lines' => [
                ['dc' => 'debit', 'account_code' => '1110', 'amount' => 5000, 'line_description' => '現金'],
                ['dc' => 'credit', 'account_code' => '1110', 'amount' => 5000, 'line_description' => '現金'],
            ],
        ]);

        $cashAccount = (new AccountModel())->where('code', '1110')->first();
        $this->assertNotNull($cashAccount);

        $result = $this->post('/cashbook/ai-suggest', [
            'transaction_text' => '現金同士の振替',
            'transaction_date' => date('Y-m-d'),
            'cash_account_id' => (string) $cashAccount['id'],
            'direction' => 'receipt',
            'amount' => '5000',
            'description' => '同一口座振替',
        ]);

        $result->assertStatus(200);
        $this->assertStringContainsString('"counterpart_account_id": 0', $result->getBody());
    }

    public function testCreateAndJournalizeFromCashbook(): void
    {
        $period = (new FiscalPeriodModel())->first();
        $cashAccount = (new AccountModel())->where('code', '1120')->first();
        $revenueAccount = (new AccountModel())->where('code', '4110')->first();

        $this->assertNotNull($period);
        $this->assertNotNull($cashAccount);
        $this->assertNotNull($revenueAccount);

        $create = $this->post('/cashbook', [
            'fiscal_period_id' => (string) $period['id'],
            'transaction_date' => date('Y-m-d'),
            'cash_account_id' => (string) $cashAccount['id'],
            'direction' => 'receipt',
            'amount' => '8000',
            'description' => '4月会費の入金',
            'counterpart_account_id' => (string) $revenueAccount['id'],
            'notes' => '出納帳テスト',
        ]);

        $create->assertStatus(302);
        $this->seeInDatabase('cashbook_entries', [
            'description' => '4月会費の入金',
            'amount' => 8000,
            'direction' => 'receipt',
        ]);

        $entry = (new CashbookEntryModel())->where('description', '4月会費の入金')->first();
        $this->assertNotNull($entry);

        $journalize = $this->post('/cashbook/' . $entry['id'] . '/journalize');
        $journalize->assertStatus(302);

        $reloaded = (new CashbookEntryModel())->find($entry['id']);
        $this->assertNotNull($reloaded);
        $this->assertNotEmpty($reloaded['journal_entry_id']);

        $this->seeInDatabase('journal_entries', [
            'id' => $reloaded['journal_entry_id'],
            'description' => '4月会費の入金',
        ]);

        $this->assertSame(1, (new JournalEntryModel())->where('id', $reloaded['journal_entry_id'])->countAllResults());
    }

    public function testJournalizeRejectsWhenCounterpartAccountIsMissing(): void
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
            'amount' => 1200,
            'description' => 'レシートのみの支出',
            'notes' => '相手科目未設定',
        ]);

        $cashbookId = (int) $model->getInsertID();
        $response = $this->post('/cashbook/' . $cashbookId . '/journalize');

        $response->assertStatus(302);
        $this->dontSeeInDatabase('journal_entries', [
            'description' => 'レシートのみの支出',
        ]);
    }

    private function setApiKey(string $value): void
    {
        putenv('openai.apiKey=' . $value);
        $_ENV['openai.apiKey'] = $value;
        $_SERVER['openai.apiKey'] = $value;
    }

    private function mockAiResponse(array $suggestion): void
    {
        $this->setApiKey('dummy-test-key');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn(json_encode([
            'choices' => [[
                'message' => [
                    'content' => json_encode($suggestion, JSON_UNESCAPED_UNICODE),
                ],
            ]],
        ], JSON_UNESCAPED_UNICODE));

        $client = $this->getMockBuilder(CURLRequest::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['post'])
            ->getMock();
        $client->expects($this->once())
            ->method('post')
            ->willReturn($response);

        Services::injectMock('curlrequest', $client);
    }
}
