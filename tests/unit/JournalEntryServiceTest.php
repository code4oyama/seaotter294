<?php

use App\Database\Seeds\AccountingBootstrapSeeder;
use App\Models\AccountModel;
use App\Models\FiscalPeriodModel;
use App\Models\JournalEntryModel;
use App\Models\JournalLineModel;
use App\Services\JournalEntryService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;

/**
 * @internal
 */
final class JournalEntryServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace = 'App';
    protected $seed = AccountingBootstrapSeeder::class;

    public function testCreateStoresBalancedEntryAndLines(): void
    {
        $service = $this->makeService();
        $period = (new FiscalPeriodModel())->first();
        $cash = $this->findAccountByCode('1110');
        $fee = $this->findAccountByCode('4110');

        $entryId = $service->create([
            'fiscal_period_id' => (int) $period['id'],
            'voucher_number' => 'JV-SVC-001',
            'entry_date' => '2026-04-07',
            'description' => 'サービス層登録テスト',
        ], [
            ['account_id' => (int) $cash['id'], 'dc' => 'debit', 'amount' => 5000, 'line_description' => '借方'],
            ['account_id' => (int) $fee['id'], 'dc' => 'credit', 'amount' => 5000, 'line_description' => '貸方'],
        ]);

        $this->assertGreaterThan(0, $entryId);
        $this->seeInDatabase('journal_entries', [
            'id' => $entryId,
            'voucher_number' => 'JV-SVC-001',
        ]);
        $this->seeInDatabase('journal_lines', [
            'journal_entry_id' => $entryId,
            'account_id' => (int) $cash['id'],
            'dc' => 'debit',
            'sort_order' => 0,
        ]);
        $this->seeInDatabase('journal_lines', [
            'journal_entry_id' => $entryId,
            'account_id' => (int) $fee['id'],
            'dc' => 'credit',
            'sort_order' => 1,
        ]);
    }

    public function testCreateRejectsWhenFewerThanTwoLinesAreProvided(): void
    {
        $service = $this->makeService();
        $period = (new FiscalPeriodModel())->first();
        $cash = $this->findAccountByCode('1110');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('仕訳明細は2行以上必要です。');

        $service->create([
            'fiscal_period_id' => (int) $period['id'],
            'voucher_number' => 'JV-SVC-002',
            'entry_date' => '2026-04-07',
            'description' => '1行だけの仕訳',
        ], [
            ['account_id' => (int) $cash['id'], 'dc' => 'debit', 'amount' => 5000, 'line_description' => '借方のみ'],
        ]);
    }

    public function testCreateRejectsInvalidDebitCreditType(): void
    {
        $service = $this->makeService();
        $period = (new FiscalPeriodModel())->first();
        $cash = $this->findAccountByCode('1110');
        $fee = $this->findAccountByCode('4110');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('借方/貸方の指定が不正です。');

        $service->create([
            'fiscal_period_id' => (int) $period['id'],
            'voucher_number' => 'JV-SVC-003',
            'entry_date' => '2026-04-07',
            'description' => '不正な借貸区分',
        ], [
            ['account_id' => (int) $cash['id'], 'dc' => 'other', 'amount' => 5000, 'line_description' => '借方'],
            ['account_id' => (int) $fee['id'], 'dc' => 'credit', 'amount' => 5000, 'line_description' => '貸方'],
        ]);
    }

    public function testCreateRejectsInvalidAccountOrAmount(): void
    {
        $service = $this->makeService();
        $period = (new FiscalPeriodModel())->first();
        $fee = $this->findAccountByCode('4110');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('勘定科目と金額を正しく入力してください。');

        $service->create([
            'fiscal_period_id' => (int) $period['id'],
            'voucher_number' => 'JV-SVC-004',
            'entry_date' => '2026-04-07',
            'description' => '不正な金額',
        ], [
            ['account_id' => 0, 'dc' => 'debit', 'amount' => 5000, 'line_description' => '借方'],
            ['account_id' => (int) $fee['id'], 'dc' => 'credit', 'amount' => 5000, 'line_description' => '貸方'],
        ]);
    }

    public function testCreateRejectsWhenDebitAndCreditAreNotPaired(): void
    {
        $service = $this->makeService();
        $period = (new FiscalPeriodModel())->first();
        $cash = $this->findAccountByCode('1110');
        $expense = $this->findAccountByCode('5110');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('借方と貸方をセットで入力してください。');

        $service->create([
            'fiscal_period_id' => (int) $period['id'],
            'voucher_number' => 'JV-SVC-005',
            'entry_date' => '2026-04-07',
            'description' => '借方のみ',
        ], [
            ['account_id' => (int) $cash['id'], 'dc' => 'debit', 'amount' => 3000, 'line_description' => '借方1'],
            ['account_id' => (int) $expense['id'], 'dc' => 'debit', 'amount' => 3000, 'line_description' => '借方2'],
        ]);
    }

    public function testUpdateThrowsWhenEntryDoesNotExist(): void
    {
        $service = $this->makeService();
        $period = (new FiscalPeriodModel())->first();
        $cash = $this->findAccountByCode('1110');
        $fee = $this->findAccountByCode('4110');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('仕訳が見つかりません。');

        $service->update(999999, [
            'fiscal_period_id' => (int) $period['id'],
            'voucher_number' => 'JV-SVC-006',
            'entry_date' => '2026-04-07',
            'description' => '存在しない更新',
        ], [
            ['account_id' => (int) $cash['id'], 'dc' => 'debit', 'amount' => 4000, 'line_description' => '借方'],
            ['account_id' => (int) $fee['id'], 'dc' => 'credit', 'amount' => 4000, 'line_description' => '貸方'],
        ]);
    }

    public function testDeleteThrowsWhenEntryDoesNotExist(): void
    {
        $service = $this->makeService();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('仕訳が見つかりません。');

        $service->delete(999999);
    }

    private function makeService(): JournalEntryService
    {
        return new JournalEntryService(
            new JournalEntryModel(),
            new JournalLineModel(),
            Database::connect()
        );
    }

    private function findAccountByCode(string $code): array
    {
        $account = (new AccountModel())->where('code', $code)->first();
        $this->assertIsArray($account);

        return $account;
    }
}
