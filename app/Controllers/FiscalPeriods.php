<?php

namespace App\Controllers;

use App\Models\FiscalPeriodModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class FiscalPeriods extends BaseController
{
    public function index()
    {
        $model = new FiscalPeriodModel();

        return view('fiscal_periods/index', [
            'title' => '会計期間一覧',
            'periods' => $model->orderBy('start_date', 'DESC')->findAll(),
        ]);
    }

    public function edit(int $id)
    {
        $model = new FiscalPeriodModel();
        $period = $this->findPeriodOrFail($model, $id);

        return view('fiscal_periods/edit', [
            'title' => '会計期間編集',
            'period' => $period,
        ]);
    }

    public function update(int $id)
    {
        $model = new FiscalPeriodModel();
        $period = $this->findPeriodOrFail($model, $id);

        $payload = [
            'name' => trim((string) $this->request->getPost('name')),
            'start_date' => trim((string) $this->request->getPost('start_date')),
            'end_date' => trim((string) $this->request->getPost('end_date')),
        ];

        if ($error = $this->validatePayload($payload, $period['id'])) {
            return redirect()->back()->withInput()->with('error', $error);
        }

        $model->update($period['id'], [
            'name' => $payload['name'],
            'start_date' => $payload['start_date'],
            'end_date' => $payload['end_date'],
        ]);

        return redirect()->to('/fiscal-periods')->with('message', '会計期間を更新しました。');
    }

    private function validatePayload(array $payload, int $ignoreId): ?string
    {
        if ($payload['name'] === '' || $payload['start_date'] === '' || $payload['end_date'] === '') {
            return '必須項目を入力してください。';
        }

        if ($payload['start_date'] > $payload['end_date']) {
            return '開始日は終了日以前にしてください。';
        }

        $builder = (new FiscalPeriodModel())->builder();
        $builder->where('name', $payload['name']);
        $builder->where('id !=', $ignoreId);

        if ($builder->countAllResults() > 0) {
            return '同じ会計期間名が既に存在します。';
        }

        return null;
    }

    private function findPeriodOrFail(FiscalPeriodModel $model, int $id): array
    {
        $period = $model->find($id);

        if (! is_array($period)) {
            throw PageNotFoundException::forPageNotFound('会計期間が見つかりません。');
        }

        return $period;
    }
}
