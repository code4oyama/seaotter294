<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<h1><?= esc($title) ?></h1>
<p>現金・預金の入出金を日付順に記録し、必要に応じて `仕訳一覧` へ転記できます。</p>

<div class="grid">
  <div class="card">
    <div>入金合計</div>
    <strong>¥<?= esc(number_format((int) $receiptTotal)) ?></strong>
  </div>
  <div class="card">
    <div>出金合計</div>
    <strong>¥<?= esc(number_format((int) $paymentTotal)) ?></strong>
  </div>
  <div class="card">
    <div>差引</div>
    <strong>¥<?= esc(number_format((int) ($receiptTotal - $paymentTotal))) ?></strong>
  </div>
</div>

<div class="stack" style="margin-top:16px;">
  <?php if (auth_can('edit')): ?>
    <a class="btn" href="/cashbook/new">新規登録</a>
    <a class="btn secondary" href="/journal-entries/new">直接仕訳を入力</a>
  <?php endif; ?>
  <a class="btn secondary" href="/cashbook/pdf" target="_blank" rel="noopener">PDF出力</a>
</div>

<?php if ($entries === []): ?>
  <div class="card" style="margin-top:16px;">まだ出納帳データがありません。</div>
<?php else: ?>
  <table class="responsive-table">
    <thead>
      <tr>
        <th>日付</th>
        <th>会計期間</th>
        <th>出納口座</th>
        <th>区分</th>
        <th>金額</th>
        <th>摘要</th>
        <th>相手科目</th>
        <th>状態</th>
        <th>操作</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($entries as $entry): ?>
        <?php $isJournalized = (int) ($entry['journal_entry_id'] ?? 0) > 0; ?>
        <tr>
          <td data-label="日付"><?= esc($entry['transaction_date']) ?></td>
          <td data-label="会計期間"><?= esc($entry['fiscal_period_name'] ?? '') ?></td>
          <td data-label="出納口座"><?= esc(trim((string) (($entry['cash_account_code'] ?? '') . ' ' . ($entry['cash_account_name'] ?? '')))) ?></td>
          <td data-label="区分">
            <span style="display:inline-block; padding:4px 8px; border-radius:9999px; background:<?= ($entry['direction'] ?? '') === 'payment' ? '#fee2e2' : '#dcfce7' ?>; color:<?= ($entry['direction'] ?? '') === 'payment' ? '#991b1b' : '#166534' ?>; font-size:0.9rem;">
              <?= ($entry['direction'] ?? '') === 'payment' ? '出金' : '入金' ?>
            </span>
          </td>
          <td data-label="金額">¥<?= esc(number_format((int) ($entry['amount'] ?? 0))) ?></td>
          <td data-label="摘要">
            <div><?= esc($entry['description']) ?></div>
            <?php if (! empty($entry['notes'])): ?>
              <div style="margin-top:4px; color:#64748b; font-size:0.92rem;"><?= esc($entry['notes']) ?></div>
            <?php endif; ?>
          </td>
          <td data-label="相手科目">
            <?php if (! empty($entry['counterpart_account_name'])): ?>
              <?= esc(trim((string) (($entry['counterpart_account_code'] ?? '') . ' ' . ($entry['counterpart_account_name'] ?? '')))) ?>
            <?php else: ?>
              <span style="color:#64748b;">未設定</span>
            <?php endif; ?>
          </td>
          <td data-label="状態">
            <?php if ($isJournalized): ?>
              <a href="/journal-entries/<?= esc((string) $entry['journal_entry_id']) ?>">仕訳化済み</a>
            <?php else: ?>
              <span style="color:#64748b;">未仕訳</span>
            <?php endif; ?>
          </td>
          <td data-label="操作">
            <?php if (auth_can('edit')): ?>
              <div class="stack">
                <a class="btn secondary" href="/cashbook/<?= esc((string) $entry['id']) ?>/edit">編集</a>
                <?php if (! $isJournalized): ?>
                  <form method="post" action="/cashbook/<?= esc((string) $entry['id']) ?>/journalize" style="display:inline;">
                    <?= csrf_field() ?>
                    <button class="btn" type="submit">仕訳へ転記</button>
                  </form>
                  <?php if (auth_can('delete')): ?>
                    <form method="post" action="/cashbook/<?= esc((string) $entry['id']) ?>/delete" onsubmit="return confirm('この出納帳データを削除しますか？');" style="display:inline;">
                      <?= csrf_field() ?>
                      <button class="btn" type="submit" style="background:#b91c1c;">削除</button>
                    </form>
                  <?php endif; ?>
                <?php endif; ?>
              </div>
            <?php else: ?>
              <span style="color:#64748b;">閲覧のみ</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
<?= $this->endSection() ?>
