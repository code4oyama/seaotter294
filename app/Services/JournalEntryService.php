<?php

namespace App\Services;

use App\Models\JournalEntryModel;
use App\Models\JournalLineModel;
use CodeIgniter\Database\BaseConnection;
use InvalidArgumentException;

class JournalEntryService
{
    public function __construct(
        private readonly JournalEntryModel $entryModel,
        private readonly JournalLineModel $lineModel,
        private readonly BaseConnection $db
    ) {
    }

    public function create(array $entryData, array $lines): int
    {
        if (count($lines) < 2) {
            throw new InvalidArgumentException('仕訳明細は2行以上必要です。');
        }

        $lines = $this->normalizeLines($lines);

        $this->db->transStart();

        $this->entryModel->insert($entryData);
        $entryId = (int) $this->entryModel->getInsertID();

        foreach ($lines as $line) {
            $line['journal_entry_id'] = $entryId;
            $this->lineModel->insert($line);
        }

        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            throw new InvalidArgumentException('仕訳の保存に失敗しました。');
        }

        return $entryId;
    }

    public function update(int $entryId, array $entryData, array $lines): void
    {
        if (! $this->entryModel->find($entryId)) {
            throw new InvalidArgumentException('仕訳が見つかりません。');
        }

        $lines = $this->normalizeLines($lines);

        $this->db->transStart();

        $this->entryModel->update($entryId, $entryData);
        $this->lineModel->where('journal_entry_id', $entryId)->delete();

        foreach ($lines as $line) {
            $line['journal_entry_id'] = $entryId;
            $this->lineModel->insert($line);
        }

        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            throw new InvalidArgumentException('仕訳の更新に失敗しました。');
        }
    }

    public function delete(int $entryId): void
    {
        if (! $this->entryModel->find($entryId)) {
            throw new InvalidArgumentException('仕訳が見つかりません。');
        }

        $this->db->transStart();
        $this->lineModel->where('journal_entry_id', $entryId)->delete();
        $this->entryModel->delete($entryId);
        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            throw new InvalidArgumentException('仕訳の削除に失敗しました。');
        }
    }

    private function normalizeLines(array $lines): array
    {
        if (count($lines) < 2) {
            throw new InvalidArgumentException('借方と貸方をセットで入力してください。');
        }

        $debitTotal = 0;
        $creditTotal = 0;
        $hasDebit = false;
        $hasCredit = false;

        foreach ($lines as $index => $line) {
            $dc = $line['dc'] ?? '';
            $amount = (int) ($line['amount'] ?? 0);
            $accountId = (int) ($line['account_id'] ?? 0);

            if (! in_array($dc, ['debit', 'credit'], true)) {
                throw new InvalidArgumentException('借方/貸方の指定が不正です。');
            }

            if ($amount <= 0 || $accountId <= 0) {
                throw new InvalidArgumentException('勘定科目と金額を正しく入力してください。');
            }

            if ($dc === 'debit') {
                $hasDebit = true;
                $debitTotal += $amount;
            } else {
                $hasCredit = true;
                $creditTotal += $amount;
            }

            $lines[$index]['amount'] = $amount;
            $lines[$index]['account_id'] = $accountId;
            $lines[$index]['sort_order'] = $index;
        }

        if (! $hasDebit || ! $hasCredit) {
            throw new InvalidArgumentException('借方と貸方をセットで入力してください。');
        }

        if ($debitTotal !== $creditTotal) {
            throw new InvalidArgumentException('借方合計と貸方合計が一致していません。');
        }

        return $lines;
    }
}
