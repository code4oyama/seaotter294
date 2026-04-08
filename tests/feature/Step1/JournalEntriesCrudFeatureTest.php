<?php

use App\Database\Seeds\DemoJournalEntriesSeeder;
use App\Models\AccountModel;
use App\Models\FiscalPeriodModel;
use App\Models\JournalEntryModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Database;

/**
 * @internal
 */
final class JournalEntriesCrudFeatureTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $refresh = true;
    protected $namespace = 'App';
    protected $seed = DemoJournalEntriesSeeder::class;

    public function testIndexShowsEditAndDeleteActions(): void
    {
        $result = $this->get('/journal-entries');

        $result->assertStatus(200);
        $result->assertSee('編集');
        $result->assertSee('削除');
    }

    public function testNewFormShowsIncreaseDecreaseIndicator(): void
    {
        $result = $this->get('/journal-entries/new');

        $result->assertStatus(200);
        $result->assertSee('増減');
    }

    public function testNewFormUsesResponsiveLineLayoutForMobile(): void
    {
        $result = $this->get('/journal-entries/new');

        $result->assertStatus(200);
        $this->assertStringContainsString('line-entry-table', $result->getBody());
        $this->assertStringContainsString('data-label=', $result->getBody());
    }

    public function testNewFormCreatesDefaultFiscalPeriodWhenNoneExists(): void
    {
        $db = Database::connect();
        $db->table('journal_lines')->emptyTable();
        $db->table('journal_entries')->emptyTable();
        $db->table('fiscal_periods')->emptyTable();

        $result = $this->get('/journal-entries/new');

        $result->assertStatus(200);
        $this->seeInDatabase('fiscal_periods', [
            'name' => date('Y') . '年度',
            'is_closed' => 0,
        ]);
    }

    public function testShowDisplaysJournalEntryDetails(): void
    {
        $entry = (new JournalEntryModel())->where('voucher_number', 'DEMO-' . date('Y') . '-003')->first();
        $this->assertNotNull($entry);

        $result = $this->get('/journal-entries/' . $entry['id']);

        $result->assertStatus(200);
        $result->assertSee('仕訳詳細');
        $result->assertSee('増加');
        $result->assertSee('減少');
    }

    public function testShowThrowsNotFoundWhenEntryIsMissing(): void
    {
        $this->expectException(PageNotFoundException::class);
        $this->expectExceptionMessage('仕訳が見つかりません。');

        $this->get('/journal-entries/999999');
    }

    public function testEditAndUpdateWork(): void
    {
        $entry = (new JournalEntryModel())->where('voucher_number', 'DEMO-' . date('Y') . '-002')->first();
        $this->assertNotNull($entry);

        $edit = $this->get('/journal-entries/' . $entry['id'] . '/edit');
        $edit->assertStatus(200);
        $edit->assertSee('仕訳編集');
        $edit->assertSee('増減');

        $update = $this->post('/journal-entries/' . $entry['id'] . '/update', [
            'fiscal_period_id' => (string) $entry['fiscal_period_id'],
            'voucher_number' => $entry['voucher_number'],
            'entry_date' => $entry['entry_date'],
            'description' => '編集後の摘要',
            'ai_request_text' => 'AIに伝えた内容: 家賃支払を仕訳化したい。',
            'dc' => ['debit', 'credit'],
            'account_id' => ['2', '27'],
            'amount' => ['51500', '51500'],
            'line_description' => ['編集後借方', '編集後貸方'],
        ]);

        $update->assertStatus(302);
        $this->seeInDatabase('journal_entries', [
            'id' => $entry['id'],
            'description' => '編集後の摘要',
            'ai_request_text' => 'AIに伝えた内容: 家賃支払を仕訳化したい。',
        ]);
        $this->seeInDatabase('journal_lines', [
            'journal_entry_id' => $entry['id'],
            'line_description' => '編集後借方',
        ]);
    }

    public function testCreateStoresAiRequestTextAndShowDisplaysIt(): void
    {
        $period = (new FiscalPeriodModel())->first();
        $cash = (new AccountModel())->where('code', '1110')->first();
        $fee = (new AccountModel())->where('code', '4110')->first();

        $this->assertNotNull($period);
        $this->assertNotNull($cash);
        $this->assertNotNull($fee);

        $response = $this->post('/journal-entries', [
            'fiscal_period_id' => (string) $period['id'],
            'voucher_number' => 'JV-AI-REQUEST-001',
            'entry_date' => date('Y-m-d'),
            'description' => 'AI入力保存テスト',
            'ai_request_text' => '4月分の会費5,000円を現金で受け取った。',
            'dc' => ['debit', 'credit'],
            'account_id' => [(string) $cash['id'], (string) $fee['id']],
            'amount' => ['5000', '5000'],
            'line_description' => ['4月会費', '4月会費'],
        ]);

        $response->assertStatus(302);
        $this->seeInDatabase('journal_entries', [
            'voucher_number' => 'JV-AI-REQUEST-001',
            'ai_request_text' => '4月分の会費5,000円を現金で受け取った。',
        ]);

        $entry = (new JournalEntryModel())->where('voucher_number', 'JV-AI-REQUEST-001')->first();
        $this->assertNotNull($entry);

        $show = $this->get('/journal-entries/' . $entry['id']);
        $show->assertStatus(200);
        $show->assertSee('AIに伝えた内容');
        $show->assertSee('4月分の会費5,000円を現金で受け取った。');
    }

    public function testDeleteRemovesJournalEntryAndLines(): void
    {
        $entry = (new JournalEntryModel())->where('voucher_number', 'DEMO-' . date('Y') . '-003')->first();
        $this->assertNotNull($entry);

        $response = $this->post('/journal-entries/' . $entry['id'] . '/delete');

        $response->assertStatus(302);
        $this->dontSeeInDatabase('journal_entries', ['id' => $entry['id']]);
        $this->dontSeeInDatabase('journal_lines', ['journal_entry_id' => $entry['id']]);
    }

    public function testDeleteRedirectsWithErrorWhenEntryIsMissing(): void
    {
        $response = $this->post('/journal-entries/999999/delete');

        $response->assertStatus(302);
    }

    public function testCreateIgnoresRowsWithoutAmount(): void
    {
        $period = (new FiscalPeriodModel())->first();
        $cash = (new AccountModel())->where('code', '1110')->first();
        $fee = (new AccountModel())->where('code', '4110')->first();

        $this->assertNotNull($period);
        $this->assertNotNull($cash);
        $this->assertNotNull($fee);

        $response = $this->post('/journal-entries', [
            'fiscal_period_id' => (string) $period['id'],
            'voucher_number' => 'JV-IGNORE-001',
            'entry_date' => date('Y-m-d'),
            'description' => '空金額行を無視するテスト',
            'dc' => ['debit', 'credit', 'debit'],
            'account_id' => [(string) $cash['id'], (string) $fee['id'], (string) $cash['id']],
            'amount' => ['5000', '5000', ''],
            'line_description' => ['借方', '貸方', '空行'],
        ]);

        $response->assertStatus(302);
        $this->seeInDatabase('journal_entries', ['voucher_number' => 'JV-IGNORE-001']);

        $entry = (new JournalEntryModel())->where('voucher_number', 'JV-IGNORE-001')->first();
        $this->assertNotNull($entry);

        $lineCount = Database::connect()
            ->table('journal_lines')
            ->where('journal_entry_id', $entry['id'])
            ->countAllResults();

        $this->assertSame(2, $lineCount);
    }

    public function testCreateRejectsWhenDebitAndCreditAreNotBothPresent(): void
    {
        $period = (new FiscalPeriodModel())->first();
        $cash = (new AccountModel())->where('code', '1110')->first();
        $expense = (new AccountModel())->where('code', '5110')->first();

        $this->assertNotNull($period);
        $this->assertNotNull($cash);
        $this->assertNotNull($expense);

        $response = $this->post('/journal-entries', [
            'fiscal_period_id' => (string) $period['id'],
            'voucher_number' => 'JV-INVALID-001',
            'entry_date' => date('Y-m-d'),
            'description' => '借方だけの不正仕訳',
            'dc' => ['debit', 'debit'],
            'account_id' => [(string) $cash['id'], (string) $expense['id']],
            'amount' => ['3000', '3000'],
            'line_description' => ['借方1', '借方2'],
        ]);

        $response->assertStatus(302);
        $this->dontSeeInDatabase('journal_entries', ['voucher_number' => 'JV-INVALID-001']);
    }
}
