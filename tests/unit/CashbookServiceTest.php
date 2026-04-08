<?php

use App\Database\Seeds\AccountingBootstrapSeeder;
use App\Models\AccountModel;
use App\Models\CashbookEntryModel;
use App\Models\FiscalPeriodModel;
use App\Models\JournalEntryModel;
use App\Models\JournalLineModel;
use App\Services\CashbookService;
use App\Services\JournalEntryService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;

/**
 * @internal
 */
final class CashbookServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace = 'App';
    protected $seed = AccountingBootstrapSeeder::class;

    public function testUpdateChangesUnjournalizedCashbookEntry(): void
    {
        $service = $this->makeService();
        $period = (new FiscalPeriodModel())->first();
        $cashAccount = $this->findAccountByCode('1110');
        $expenseAccount = $this->findAccountByCode('5180');

        $entryId = $service->create($this->validPayload([
            'fiscal_period_id' => (int) $period['id'],
            'cash_account_id' => (int) $cashAccount['id'],
            'description' => '更新前データ',
        ]));

        $service->update($entryId, $this->validPayload([
            'fiscal_period_id' => (int) $period['id'],
            'cash_account_id' => (int) $cashAccount['id'],
            'direction' => 'payment',
            'amount' => 3200,
            'description' => '更新後データ',
            'counterpart_account_id' => (int) $expenseAccount['id'],
            'notes' => '更新メモ',
        ]));

        $this->seeInDatabase('cashbook_entries', [
            'id' => $entryId,
            'direction' => 'payment',
            'amount' => 3200,
            'description' => '更新後データ',
            'counterpart_account_id' => (int) $expenseAccount['id'],
        ]);
    }

    public function testUpdateRejectsWhenEntryIsAlreadyJournalized(): void
    {
        $service = $this->makeService();
        $entryId = $service->create($this->validPayload([
            'description' => '仕訳化済み更新テスト',
        ]));
        $service->journalize($entryId);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('仕訳化済みの出納帳は編集できません。');

        $service->update($entryId, $this->validPayload([
            'description' => '更新不可データ',
        ]));
    }

    public function testDeleteRemovesUnjournalizedCashbookEntry(): void
    {
        $service = $this->makeService();
        $entryId = $service->create($this->validPayload([
            'description' => '削除対象データ',
        ]));

        $service->delete($entryId);

        $this->dontSeeInDatabase('cashbook_entries', ['id' => $entryId]);
    }

    public function testDeleteRejectsWhenEntryIsAlreadyJournalized(): void
    {
        $service = $this->makeService();
        $entryId = $service->create($this->validPayload([
            'description' => '仕訳化済み削除テスト',
        ]));
        $service->journalize($entryId);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('仕訳化済みの出納帳は削除できません。');

        $service->delete($entryId);
    }

    public function testCreateRejectsWhenTransactionDateIsOutsideFiscalPeriod(): void
    {
        $service = $this->makeService();
        $period = (new FiscalPeriodModel())->first();
        $this->assertIsArray($period);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('取引日は会計期間内にしてください。');

        $service->create($this->validPayload([
            'fiscal_period_id' => (int) $period['id'],
            'transaction_date' => ((int) substr((string) $period['start_date'], 0, 4) - 1) . '-12-31',
        ]));
    }

    public function testCreateRejectsWhenCashAccountIsNotAsset(): void
    {
        $service = $this->makeService();
        $revenueAccount = $this->findAccountByCode('4110');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('出納口座には資産科目を選択してください。');

        $service->create($this->validPayload([
            'cash_account_id' => (int) $revenueAccount['id'],
        ]));
    }

    public function testCreateRejectsWhenCounterpartMatchesCashAccount(): void
    {
        $service = $this->makeService();
        $cashAccount = $this->findAccountByCode('1110');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('相手勘定科目は出納口座と別の科目を選択してください。');

        $service->create($this->validPayload([
            'cash_account_id' => (int) $cashAccount['id'],
            'counterpart_account_id' => (int) $cashAccount['id'],
        ]));
    }

    public function testJournalizeCreatesPaymentEntryForPaymentDirection(): void
    {
        $service = $this->makeService();
        $cashAccount = $this->findAccountByCode('1110');
        $expenseAccount = $this->findAccountByCode('5180');

        $entryId = $service->create($this->validPayload([
            'cash_account_id' => (int) $cashAccount['id'],
            'direction' => 'payment',
            'amount' => 4500,
            'description' => '消耗品の支払',
            'counterpart_account_id' => (int) $expenseAccount['id'],
        ]));

        $journalEntryId = $service->journalize($entryId);

        $this->assertGreaterThan(0, $journalEntryId);
        $this->seeInDatabase('journal_lines', [
            'journal_entry_id' => $journalEntryId,
            'account_id' => (int) $expenseAccount['id'],
            'dc' => 'debit',
            'amount' => 4500,
        ]);
        $this->seeInDatabase('journal_lines', [
            'journal_entry_id' => $journalEntryId,
            'account_id' => (int) $cashAccount['id'],
            'dc' => 'credit',
            'amount' => 4500,
        ]);
    }

    public function testJournalizeRejectsWhenEntryIsAlreadyJournalized(): void
    {
        $service = $this->makeService();
        $entryId = $service->create($this->validPayload([
            'description' => '二重仕訳化テスト',
        ]));
        $service->journalize($entryId);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('この出納帳はすでに仕訳化済みです。');

        $service->journalize($entryId);
    }

    private function makeService(): CashbookService
    {
        $db = Database::connect();

        return new CashbookService(
            new CashbookEntryModel(),
            new AccountModel(),
            new FiscalPeriodModel(),
            new JournalEntryService(new JournalEntryModel(), new JournalLineModel(), $db),
            $db
        );
    }

    private function validPayload(array $overrides = []): array
    {
        $period = (new FiscalPeriodModel())->first();
        $this->assertIsArray($period);
        $cashAccount = $this->findAccountByCode('1110');
        $revenueAccount = $this->findAccountByCode('4110');

        return array_merge([
            'fiscal_period_id' => (int) $period['id'],
            'transaction_date' => date('Y-m-d'),
            'cash_account_id' => (int) $cashAccount['id'],
            'direction' => 'receipt',
            'amount' => 5000,
            'description' => '出納帳サービスのテストデータ',
            'counterpart_account_id' => (int) $revenueAccount['id'],
            'notes' => 'テストメモ',
        ], $overrides);
    }

    private function findAccountByCode(string $code): array
    {
        $account = (new AccountModel())->where('code', $code)->first();
        $this->assertIsArray($account);

        return $account;
    }
}
