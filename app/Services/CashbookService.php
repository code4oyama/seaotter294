<?php

namespace App\Services;

use App\Models\AccountModel;
use App\Models\CashbookEntryModel;
use App\Models\FiscalPeriodModel;
use App\Models\JournalEntryModel;
use App\Models\JournalLineModel;
use CodeIgniter\Database\BaseConnection;
use InvalidArgumentException;

class CashbookService
{
    public function __construct(
        private readonly CashbookEntryModel $cashbookModel,
        private readonly AccountModel $accountModel,
        private readonly FiscalPeriodModel $fiscalPeriodModel,
        private readonly JournalEntryService $journalEntryService,
        private readonly BaseConnection $db
    ) {
    }

    public static function make(BaseConnection $db): self
    {
        return new self(
            new CashbookEntryModel(),
            new AccountModel(),
            new FiscalPeriodModel(),
            new JournalEntryService(new JournalEntryModel(), new JournalLineModel(), $db),
            $db
        );
    }

    public function create(array $data): int
    {
        $payload = $this->normalizePayload($data);
        $this->cashbookModel->insert($payload);

        return (int) $this->cashbookModel->getInsertID();
    }

    public function update(int $id, array $data): void
    {
        $entry = $this->findOrFail($id);

        if ((int) ($entry['journal_entry_id'] ?? 0) > 0) {
            throw new InvalidArgumentException('仕訳化済みの出納帳は編集できません。');
        }

        $payload = $this->normalizePayload($data);
        $this->cashbookModel->update($id, $payload);
    }

    public function delete(int $id): void
    {
        $entry = $this->findOrFail($id);

        if ((int) ($entry['journal_entry_id'] ?? 0) > 0) {
            throw new InvalidArgumentException('仕訳化済みの出納帳は削除できません。');
        }

        $this->cashbookModel->delete($id);
    }

    public function journalize(int $id): int
    {
        $cashbookEntry = $this->findOrFail($id);

        if ((int) ($cashbookEntry['journal_entry_id'] ?? 0) > 0) {
            throw new InvalidArgumentException('この出納帳はすでに仕訳化済みです。');
        }

        $period = $this->loadPeriod((int) ($cashbookEntry['fiscal_period_id'] ?? 0));
        if ((bool) ($period['is_closed'] ?? false)) {
            throw new InvalidArgumentException('締め済みの会計期間は仕訳化できません。');
        }

        $cashAccount = $this->loadAccount((int) ($cashbookEntry['cash_account_id'] ?? 0), '出納口座');
        $counterpartAccountId = (int) ($cashbookEntry['counterpart_account_id'] ?? 0);
        if ($counterpartAccountId <= 0) {
            throw new InvalidArgumentException('相手勘定科目を設定してから仕訳化してください。');
        }

        $counterpartAccount = $this->loadAccount($counterpartAccountId, '相手勘定科目');
        $amount = (int) ($cashbookEntry['amount'] ?? 0);
        $description = trim((string) ($cashbookEntry['description'] ?? ''));
        $lineDescription = $description !== '' ? $description : '出納帳転記';

        $lines = (string) ($cashbookEntry['direction'] ?? '') === 'payment'
            ? [
                [
                    'account_id' => (int) $counterpartAccount['id'],
                    'dc' => 'debit',
                    'amount' => $amount,
                    'line_description' => $lineDescription,
                ],
                [
                    'account_id' => (int) $cashAccount['id'],
                    'dc' => 'credit',
                    'amount' => $amount,
                    'line_description' => $lineDescription,
                ],
            ]
            : [
                [
                    'account_id' => (int) $cashAccount['id'],
                    'dc' => 'debit',
                    'amount' => $amount,
                    'line_description' => $lineDescription,
                ],
                [
                    'account_id' => (int) $counterpartAccount['id'],
                    'dc' => 'credit',
                    'amount' => $amount,
                    'line_description' => $lineDescription,
                ],
            ];

        $entryData = [
            'fiscal_period_id' => (int) $cashbookEntry['fiscal_period_id'],
            'voucher_number' => $this->makeVoucherNumber($cashbookEntry),
            'entry_date' => (string) $cashbookEntry['transaction_date'],
            'description' => $lineDescription,
        ];

        $this->db->transStart();
        $journalEntryId = $this->journalEntryService->create($entryData, $lines);
        $this->cashbookModel->update($id, [
            'journal_entry_id' => $journalEntryId,
            'journalized_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            throw new InvalidArgumentException('出納帳から仕訳の生成に失敗しました。');
        }

        return $journalEntryId;
    }

    private function normalizePayload(array $data): array
    {
        $fiscalPeriodId = (int) ($data['fiscal_period_id'] ?? 0);
        $transactionDate = trim((string) ($data['transaction_date'] ?? ''));
        $cashAccountId = (int) ($data['cash_account_id'] ?? 0);
        $direction = trim((string) ($data['direction'] ?? ''));
        $amount = (int) ($data['amount'] ?? 0);
        $description = trim((string) ($data['description'] ?? ''));
        $counterpartAccountId = (int) ($data['counterpart_account_id'] ?? 0);
        $notes = trim((string) ($data['notes'] ?? ''));

        if ($fiscalPeriodId <= 0 || $transactionDate === '' || $cashAccountId <= 0 || $description === '') {
            throw new InvalidArgumentException('必須項目を入力してください。');
        }

        if (! in_array($direction, ['receipt', 'payment'], true)) {
            throw new InvalidArgumentException('入出金区分が不正です。');
        }

        if ($amount <= 0) {
            throw new InvalidArgumentException('金額は1円以上の整数で入力してください。');
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $transactionDate) || strtotime($transactionDate) === false) {
            throw new InvalidArgumentException('取引日が不正です。');
        }

        $period = $this->loadPeriod($fiscalPeriodId);
        if ((bool) ($period['is_closed'] ?? false)) {
            throw new InvalidArgumentException('締め済みの会計期間には登録できません。');
        }

        if ($transactionDate < (string) $period['start_date'] || $transactionDate > (string) $period['end_date']) {
            throw new InvalidArgumentException('取引日は会計期間内にしてください。');
        }

        $cashAccount = $this->loadAccount($cashAccountId, '出納口座');
        if ((string) ($cashAccount['category'] ?? '') !== 'asset') {
            throw new InvalidArgumentException('出納口座には資産科目を選択してください。');
        }

        if ($counterpartAccountId > 0) {
            if ($counterpartAccountId === $cashAccountId) {
                throw new InvalidArgumentException('相手勘定科目は出納口座と別の科目を選択してください。');
            }

            $this->loadAccount($counterpartAccountId, '相手勘定科目');
        }

        return [
            'fiscal_period_id' => $fiscalPeriodId,
            'transaction_date' => $transactionDate,
            'cash_account_id' => $cashAccountId,
            'direction' => $direction,
            'amount' => $amount,
            'description' => $description,
            'counterpart_account_id' => $counterpartAccountId > 0 ? $counterpartAccountId : null,
            'notes' => $notes !== '' ? $notes : null,
        ];
    }

    private function findOrFail(int $id): array
    {
        $entry = $this->cashbookModel->find($id);

        if (! is_array($entry)) {
            throw new InvalidArgumentException('出納帳データが見つかりません。');
        }

        return $entry;
    }

    private function loadPeriod(int $id): array
    {
        $period = $this->fiscalPeriodModel->find($id);

        if (! is_array($period)) {
            throw new InvalidArgumentException('会計期間が見つかりません。');
        }

        return $period;
    }

    private function loadAccount(int $id, string $label): array
    {
        $account = $this->accountModel->find($id);

        if (! is_array($account) || ! (bool) ($account['is_active'] ?? false)) {
            throw new InvalidArgumentException($label . 'が見つかりません。');
        }

        return $account;
    }

    private function makeVoucherNumber(array $cashbookEntry): string
    {
        $dateCode = preg_replace('/[^0-9]/', '', (string) ($cashbookEntry['transaction_date'] ?? '')) ?: date('Ymd');

        return sprintf('CB-%s-%05d', $dateCode, (int) ($cashbookEntry['id'] ?? 0));
    }
}
