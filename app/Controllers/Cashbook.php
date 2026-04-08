<?php

namespace App\Controllers;

use App\Libraries\FinancialStatementPdf;
use App\Models\AccountModel;
use App\Models\CashbookEntryModel;
use App\Models\FiscalPeriodModel;
use App\Services\CashbookService;
use App\Services\JournalEntryAiService;
use CodeIgniter\Exceptions\PageNotFoundException;
use Config\Database;
use InvalidArgumentException;
use Throwable;

class Cashbook extends BaseController
{
    public function index()
    {
        [$entries, $receiptTotal, $paymentTotal] = $this->loadEntriesAndTotals();

        return view('cashbook/index', [
            'title' => '出納帳',
            'entries' => $entries,
            'receiptTotal' => $receiptTotal,
            'paymentTotal' => $paymentTotal,
        ]);
    }

    public function pdf()
    {
        [$entries, $receiptTotal, $paymentTotal] = $this->loadEntriesAndTotals();

        $pdf = FinancialStatementPdf::createCashbookPdf($entries, [
            'receiptTotal' => $receiptTotal,
            'paymentTotal' => $paymentTotal,
            'balance' => $receiptTotal - $paymentTotal,
        ]);

        $filename = sprintf('cashbook_%s.pdf', date('Ymd_His'));

        return $this->response
            ->setHeader('Content-Disposition', 'inline; filename="' . $filename . '"')
            ->setHeader('Content-Type', 'application/pdf')
            ->setBody($pdf);
    }

    public function new()
    {
        return view('cashbook/new', $this->buildFormData('出納帳を登録'));
    }

    public function create()
    {
        $service = CashbookService::make(Database::connect());

        try {
            $service->create($this->collectPayload());
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/cashbook')->with('message', '出納帳を登録しました。');
    }

    public function edit(int $id)
    {
        return view('cashbook/edit', $this->buildFormData('出納帳を編集', $this->findEntryOrFail($id)));
    }

    public function update(int $id)
    {
        $service = CashbookService::make(Database::connect());

        try {
            $service->update($id, $this->collectPayload());
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/cashbook')->with('message', '出納帳を更新しました。');
    }

    public function delete(int $id)
    {
        $service = CashbookService::make(Database::connect());

        try {
            $service->delete($id);
        } catch (InvalidArgumentException $e) {
            return redirect()->to('/cashbook')->with('error', $e->getMessage());
        }

        return redirect()->to('/cashbook')->with('message', '出納帳を削除しました。');
    }

    public function journalize(int $id)
    {
        $service = CashbookService::make(Database::connect());

        try {
            $journalEntryId = $service->journalize($id);
        } catch (InvalidArgumentException $e) {
            return redirect()->to('/cashbook')->with('error', $e->getMessage());
        }

        return redirect()->to('/journal-entries/' . $journalEntryId)->with('message', '出納帳から仕訳を生成しました。');
    }

    public function aiSuggest()
    {
        $payload = $this->collectPayload();
        $transactionText = trim((string) $this->request->getPost('transaction_text'));
        $cashAccount = (new AccountModel())->find((int) ($payload['cash_account_id'] ?? 0));

        if ($transactionText === '') {
            $transactionText = $this->buildAiTransactionText($payload, is_array($cashAccount) ? $cashAccount : null);
        }

        $service = new JournalEntryAiService();
        $accounts = (new AccountModel())
            ->where('is_active', 1)
            ->orderBy('code', 'ASC')
            ->findAll();

        try {
            $suggestion = $service->suggest($transactionText, $accounts, (string) ($payload['transaction_date'] ?? ''));
            $counterpart = $this->extractCounterpart($suggestion['lines'] ?? [], (int) ($payload['cash_account_id'] ?? 0), (string) ($payload['direction'] ?? ''));
        } catch (InvalidArgumentException $e) {
            return $this->response->setStatusCode(422)->setJSON([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        } catch (Throwable $e) {
            log_message('error', 'AI cashbook suggestion failed: {message}', ['message' => $e->getMessage()]);

            return $this->response->setStatusCode(500)->setJSON([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }

        return $this->response->setJSON([
            'status' => 'ok',
            'suggestion' => [
                'description' => (string) ($suggestion['description'] ?? ''),
                'note' => (string) ($suggestion['note'] ?? ''),
                'counterpart_account_id' => (int) ($counterpart['account_id'] ?? 0),
                'counterpart_account_label' => isset($counterpart['account_code'], $counterpart['account_name'])
                    ? (string) ($counterpart['account_code'] . ' ' . $counterpart['account_name'])
                    : '',
                'lines' => $suggestion['lines'] ?? [],
            ],
        ]);
    }

    private function loadEntriesAndTotals(): array
    {
        $entries = (new CashbookEntryModel())
            ->select('cashbook_entries.*, fiscal_periods.name AS fiscal_period_name, cash_account.code AS cash_account_code, cash_account.name AS cash_account_name, counterpart_account.code AS counterpart_account_code, counterpart_account.name AS counterpart_account_name')
            ->join('fiscal_periods', 'fiscal_periods.id = cashbook_entries.fiscal_period_id', 'left')
            ->join('accounts AS cash_account', 'cash_account.id = cashbook_entries.cash_account_id', 'left')
            ->join('accounts AS counterpart_account', 'counterpart_account.id = cashbook_entries.counterpart_account_id', 'left')
            ->orderBy('transaction_date', 'DESC')
            ->orderBy('id', 'DESC')
            ->findAll();

        $receiptTotal = 0;
        $paymentTotal = 0;
        foreach ($entries as $entry) {
            if ((string) ($entry['direction'] ?? '') === 'payment') {
                $paymentTotal += (int) ($entry['amount'] ?? 0);
                continue;
            }

            $receiptTotal += (int) ($entry['amount'] ?? 0);
        }

        return [$entries, $receiptTotal, $paymentTotal];
    }

    private function buildFormData(string $title, array $entry = []): array
    {
        $periodModel = new FiscalPeriodModel();
        $periods = $periodModel->orderBy('start_date', 'ASC')->findAll();

        if ($periods === []) {
            $periodModel->insert([
                'name' => date('Y') . '年度',
                'start_date' => date('Y-01-01'),
                'end_date' => date('Y-12-31'),
                'is_closed' => 0,
            ]);
            $periods = $periodModel->orderBy('start_date', 'ASC')->findAll();
        }

        $accountModel = new AccountModel();

        return [
            'title' => $title,
            'action' => $entry === [] ? '/cashbook' : '/cashbook/' . (int) ($entry['id'] ?? 0) . '/update',
            'entry' => $entry,
            'periods' => $periods,
            'cashAccounts' => $accountModel->where('is_active', 1)->like('code', '11', 'after')->orderBy('code', 'ASC')->findAll(),
            'counterpartAccounts' => $accountModel->where('is_active', 1)->orderBy('code', 'ASC')->findAll(),
            'aiEnabled' => trim((string) env('openai.apiKey', '')) !== '',
        ];
    }

    private function collectPayload(): array
    {
        return [
            'fiscal_period_id' => (int) $this->request->getPost('fiscal_period_id'),
            'transaction_date' => trim((string) $this->request->getPost('transaction_date')),
            'cash_account_id' => (int) $this->request->getPost('cash_account_id'),
            'direction' => trim((string) $this->request->getPost('direction')),
            'amount' => (int) $this->request->getPost('amount'),
            'description' => trim((string) $this->request->getPost('description')),
            'counterpart_account_id' => (int) $this->request->getPost('counterpart_account_id'),
            'notes' => trim((string) $this->request->getPost('notes')),
        ];
    }

    private function findEntryOrFail(int $id): array
    {
        $entry = (new CashbookEntryModel())->find($id);

        if (! is_array($entry)) {
            throw PageNotFoundException::forPageNotFound('出納帳データが見つかりません。');
        }

        return $entry;
    }

    private function buildAiTransactionText(array $payload, ?array $cashAccount): string
    {
        $directionLabel = (string) ($payload['direction'] ?? '') === 'payment' ? '出金' : '入金';
        $cashAccountLabel = is_array($cashAccount)
            ? trim((string) ($cashAccount['code'] . ' ' . $cashAccount['name']))
            : '未選択';

        return implode("\n", array_filter([
            '出納帳の1件から、NPO会計向けの仕訳候補を提案してください。',
            '出納口座: ' . $cashAccountLabel,
            '区分: ' . $directionLabel,
            '金額: ' . number_format((int) ($payload['amount'] ?? 0)) . '円',
            '取引日: ' . ((string) ($payload['transaction_date'] ?? '') !== '' ? (string) $payload['transaction_date'] : date('Y-m-d')),
            '摘要: ' . ((string) ($payload['description'] ?? '') !== '' ? (string) $payload['description'] : '未入力'),
            trim((string) ($payload['notes'] ?? '')) !== '' ? '補足: ' . trim((string) $payload['notes']) : '',
        ]));
    }

    private function extractCounterpart(array $lines, int $cashAccountId, string $direction): ?array
    {
        $expectedDc = $direction === 'payment' ? 'debit' : 'credit';

        foreach ($lines as $line) {
            if ((string) ($line['dc'] ?? '') === $expectedDc && (int) ($line['account_id'] ?? 0) !== $cashAccountId) {
                return $line;
            }
        }

        foreach ($lines as $line) {
            if ((int) ($line['account_id'] ?? 0) !== $cashAccountId) {
                return $line;
            }
        }

        return null;
    }
}
