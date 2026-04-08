<?php

namespace App\Controllers;

use App\Models\AccountModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use Config\Database;

class Accounts extends BaseController
{
    private const CATEGORY_LABELS = [
        'asset' => '資産',
        'liability' => '負債',
        'net_asset' => '正味財産',
        'revenue' => '収益',
        'expense' => '費用',
    ];

    public function index()
    {
        $model = new AccountModel();

        return view('accounts/index', [
            'title' => '勘定科目一覧',
            'accounts' => $model->orderBy('code', 'ASC')->findAll(),
            'categoryLabels' => self::CATEGORY_LABELS,
        ]);
    }

    public function new()
    {
        return view('accounts/new', [
            'title' => '勘定科目登録',
            'categories' => array_keys(self::CATEGORY_LABELS),
            'categoryLabels' => self::CATEGORY_LABELS,
        ]);
    }

    public function create()
    {
        $model = new AccountModel();
        $payload = $this->buildPayload();

        if ($error = $this->validatePayload($payload)) {
            return redirect()->back()->withInput()->with('error', $error);
        }

        $model->insert($payload);

        return redirect()->to('/accounts')->with('message', '勘定科目を登録しました。');
    }

    public function edit(int $id)
    {
        $model = new AccountModel();
        $account = $this->findAccountOrFail($model, $id);

        return view('accounts/edit', [
            'title' => '勘定科目編集',
            'account' => $account,
            'categories' => array_keys(self::CATEGORY_LABELS),
            'categoryLabels' => self::CATEGORY_LABELS,
        ]);
    }

    public function update(int $id)
    {
        $model = new AccountModel();
        $account = $this->findAccountOrFail($model, $id);
        $payload = $this->buildPayload();

        if ($error = $this->validatePayload($payload, $id)) {
            return redirect()->back()->withInput()->with('error', $error);
        }

        $model->update($account['id'], $payload);

        return redirect()->to('/accounts')->with('message', '勘定科目を更新しました。');
    }

    public function delete(int $id)
    {
        $model = new AccountModel();
        $account = $this->findAccountOrFail($model, $id);

        $isUsed = Database::connect()
            ->table('journal_lines')
            ->where('account_id', $account['id'])
            ->countAllResults() > 0;

        if ($isUsed) {
            return redirect()->to('/accounts')->with('error', 'この勘定科目は仕訳で使用されているため削除できません。');
        }

        $model->delete($account['id']);

        return redirect()->to('/accounts')->with('message', '勘定科目を削除しました。');
    }

    private function buildPayload(): array
    {
        return [
            'code' => trim((string) $this->request->getPost('code')),
            'name' => trim((string) $this->request->getPost('name')),
            'category' => trim((string) $this->request->getPost('category')),
            'is_active' => $this->request->getPost('is_active') ? 1 : 0,
        ];
    }

    private function validatePayload(array $payload, ?int $ignoreId = null): ?string
    {
        if ($payload['code'] === '' || $payload['name'] === '' || $payload['category'] === '') {
            return '必須項目を入力してください。';
        }

        if (! array_key_exists($payload['category'], self::CATEGORY_LABELS)) {
            return '区分が不正です。';
        }

        $builder = (new AccountModel())->builder();
        $builder->where('code', $payload['code']);
        if ($ignoreId !== null) {
            $builder->where('id !=', $ignoreId);
        }

        if ($builder->countAllResults() > 0) {
            return '同じ勘定科目コードが既に存在します。';
        }

        return null;
    }

    private function findAccountOrFail(AccountModel $model, int $id): array
    {
        $account = $model->find($id);

        if (! is_array($account)) {
            throw PageNotFoundException::forPageNotFound('勘定科目が見つかりません。');
        }

        return $account;
    }
}
