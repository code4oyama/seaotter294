<?php

namespace App\Controllers;

use App\Models\AccountModel;
use App\Models\FiscalPeriodModel;
use App\Models\JournalEntryModel;
use App\Services\JournalEntryAiService;
use App\Services\JournalEntryService;
use Config\Database;
use InvalidArgumentException;
use Throwable;

class JournalEntries extends BaseController
{
    public function index()
    {
        $entryModel = new JournalEntryModel();

        $entries = $entryModel
            ->select('journal_entries.*, fiscal_periods.name as fiscal_period_name')
            ->join('fiscal_periods', 'fiscal_periods.id = journal_entries.fiscal_period_id', 'left')
            ->orderBy('entry_date', 'DESC')
            ->orderBy('id', 'DESC')
            ->findAll();

        return view('journal_entries/index', [
            'title' => '仕訳一覧',
            'entries' => $entries,
        ]);
    }

    public function new()
    {
        $periodModel = new FiscalPeriodModel();
        $accountModel = new AccountModel();

        $periods = $periodModel->where('is_closed', 0)->orderBy('start_date', 'ASC')->findAll();

        if ($periods === []) {
            $periodModel->insert([
                'name' => date('Y') . '年度',
                'start_date' => date('Y-01-01'),
                'end_date' => date('Y-12-31'),
                'is_closed' => 0,
            ]);
            $periods = $periodModel->where('is_closed', 0)->orderBy('start_date', 'ASC')->findAll();
        }

        return view('journal_entries/new', [
            'title' => '仕訳入力',
            'periods' => $periods,
            'accounts' => $accountModel->where('is_active', 1)->orderBy('code', 'ASC')->findAll(),
            'defaultVoucherNumber' => $this->generateVoucherNumber(),
            'aiEnabled' => trim((string) env('openai.apiKey', '')) !== '',
        ]);
    }

    public function create()
    {
        [$entryData, $lines] = $this->collectEntryPayload();
        $service = new JournalEntryService(new JournalEntryModel(), new \App\Models\JournalLineModel(), Database::connect());

        try {
            $entryId = $service->create($entryData, $lines);
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/journal-entries/' . $entryId)->with('message', '仕訳を登録しました。');
    }

    public function edit(int $id)
    {
        $entryModel = new JournalEntryModel();
        $periodModel = new FiscalPeriodModel();
        $accountModel = new AccountModel();

        $entry = $entryModel->find($id);
        if (! $entry) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('仕訳が見つかりません。');
        }

        $lines = Database::connect()
            ->table('journal_lines')
            ->where('journal_entry_id', $id)
            ->orderBy('sort_order', 'ASC')
            ->get()
            ->getResultArray();

        return view('journal_entries/edit', [
            'title' => '仕訳編集',
            'entry' => $entry,
            'lines' => $lines,
            'periods' => $periodModel->where('is_closed', 0)->orderBy('start_date', 'ASC')->findAll(),
            'accounts' => $accountModel->where('is_active', 1)->orderBy('code', 'ASC')->findAll(),
            'aiEnabled' => trim((string) env('openai.apiKey', '')) !== '',
        ]);
    }

    public function update(int $id)
    {
        [$entryData, $lines] = $this->collectEntryPayload();
        $service = new JournalEntryService(new JournalEntryModel(), new \App\Models\JournalLineModel(), Database::connect());

        try {
            $service->update($id, $entryData, $lines);
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/journal-entries/' . $id)->with('message', '仕訳を更新しました。');
    }

    public function delete(int $id)
    {
        $service = new JournalEntryService(new JournalEntryModel(), new \App\Models\JournalLineModel(), Database::connect());

        try {
            $service->delete($id);
        } catch (InvalidArgumentException $e) {
            return redirect()->to('/journal-entries')->with('error', $e->getMessage());
        }

        return redirect()->to('/journal-entries')->with('message', '仕訳を削除しました。');
    }

    public function aiSuggest()
    {
        $transactionText = trim((string) $this->request->getPost('transaction_text'));
        $entryDate = trim((string) $this->request->getPost('entry_date'));

        $accounts = (new AccountModel())
            ->where('is_active', 1)
            ->orderBy('code', 'ASC')
            ->findAll();

        $service = new JournalEntryAiService();

        try {
            $suggestion = $service->suggest($transactionText, $accounts, $entryDate !== '' ? $entryDate : null);
        } catch (InvalidArgumentException $e) {
            return $this->response->setStatusCode(422)->setJSON([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        } catch (Throwable $e) {
            log_message('error', 'AI journal suggestion failed: {message}', ['message' => $e->getMessage()]);

            return $this->response->setStatusCode(500)->setJSON([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }

        return $this->response->setJSON([
            'status' => 'ok',
            'suggestion' => $suggestion,
        ]);
    }

    public function show(int $id)
    {
        $entryModel = new JournalEntryModel();

        $entry = $entryModel
            ->select('journal_entries.*, fiscal_periods.name as fiscal_period_name')
            ->join('fiscal_periods', 'fiscal_periods.id = journal_entries.fiscal_period_id', 'left')
            ->find($id);

        if (! $entry) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('仕訳が見つかりません。');
        }

        $lines = Database::connect()
            ->table('journal_lines')
            ->select('journal_lines.*, accounts.code as account_code, accounts.name as account_name, accounts.category as account_category')
            ->join('accounts', 'accounts.id = journal_lines.account_id')
            ->where('journal_entry_id', $id)
            ->orderBy('sort_order', 'ASC')
            ->get()
            ->getResultArray();

        return view('journal_entries/show', [
            'title' => '仕訳詳細',
            'entry' => $entry,
            'lines' => $lines,
        ]);
    }

    private function collectEntryPayload(): array
    {
        $aiRequestText = trim((string) $this->request->getPost('ai_request_text'));

        $entryData = [
            'fiscal_period_id' => (int) $this->request->getPost('fiscal_period_id'),
            'voucher_number' => trim((string) $this->request->getPost('voucher_number')),
            'entry_date' => (string) $this->request->getPost('entry_date'),
            'description' => trim((string) $this->request->getPost('description')),
            'ai_request_text' => $aiRequestText !== '' ? $aiRequestText : null,
        ];

        $accountIds = (array) $this->request->getPost('account_id');
        $dcs = (array) $this->request->getPost('dc');
        $amounts = (array) $this->request->getPost('amount');
        $lineDescriptions = (array) $this->request->getPost('line_description');

        $lines = [];
        $lineCount = max(count($accountIds), count($dcs), count($amounts));

        for ($i = 0; $i < $lineCount; $i++) {
            $rawAmount = trim((string) ($amounts[$i] ?? ''));

            if ($rawAmount === '') {
                continue;
            }

            $lines[] = [
                'account_id' => (int) ($accountIds[$i] ?? 0),
                'dc' => (string) ($dcs[$i] ?? ''),
                'amount' => (int) $rawAmount,
                'line_description' => trim((string) ($lineDescriptions[$i] ?? '')),
            ];
        }

        return [$entryData, $lines];
    }

    private function generateVoucherNumber(): string
    {
        $prefix = date('Ymd');
        $count = (new JournalEntryModel())
            ->where('entry_date', date('Y-m-d'))
            ->countAllResults() + 1;

        return sprintf('JV-%s-%03d', $prefix, $count);
    }
}
