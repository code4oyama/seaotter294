<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<h1><?= esc($title) ?></h1>
<table class="detail-table">
  <tr><th>伝票番号</th><td><?= esc($entry['voucher_number']) ?></td></tr>
  <tr><th>取引日</th><td><?= esc($entry['entry_date']) ?></td></tr>
  <tr><th>会計期間</th><td><?= esc($entry['fiscal_period_name'] ?? '') ?></td></tr>
  <tr><th>摘要</th><td><?= esc($entry['description']) ?></td></tr>
  <?php if (! empty($entry['ai_request_text'])): ?>
    <tr>
      <th>AIに伝えた内容</th>
      <td style="white-space:pre-wrap;"><?= esc($entry['ai_request_text']) ?></td>
    </tr>
  <?php endif; ?>
</table>

<h2 style="margin-top:20px; font-size:1.1rem;">明細</h2>
<?php
$categoryLabels = [
  'asset' => '資産',
  'liability' => '負債',
  'net_asset' => '正味財産',
  'revenue' => '収益',
  'expense' => '費用',
];
?>
<table class="responsive-table">
  <thead>
    <tr>
      <th>借貸</th>
      <th>勘定科目</th>
      <th>増減</th>
      <th>明細摘要</th>
      <th>金額</th>
    </tr>
  </thead>
  <tbody>
    <?php $debit = 0; $credit = 0; ?>
    <?php foreach ($lines as $line): ?>
      <?php
      if ($line['dc'] === 'debit') {
          $debit += (int) $line['amount'];
      } else {
          $credit += (int) $line['amount'];
      }

      $category = (string) ($line['account_category'] ?? '');
      $categoryLabel = $categoryLabels[$category] ?? $category;
      $changeLabel = '―';
      if (in_array($category, ['asset', 'expense'], true)) {
          $changeLabel = $line['dc'] === 'debit' ? '増加' : '減少';
      } elseif (in_array($category, ['liability', 'net_asset', 'revenue'], true)) {
          $changeLabel = $line['dc'] === 'credit' ? '増加' : '減少';
      }
      ?>
      <tr>
        <td data-label="借貸"><?= $line['dc'] === 'debit' ? '借方' : '貸方' ?></td>
        <td data-label="勘定科目">
          <?= esc($line['account_code'] . ' ' . $line['account_name']) ?><br>
          <span style="font-size:0.9rem; color:#64748b;">区分: <?= esc($categoryLabel) ?></span>
        </td>
        <td data-label="増減">
          <strong style="color: <?= $changeLabel === '増加' ? '#166534' : ($changeLabel === '減少' ? '#b91c1c' : '#475569') ?>;">
            <?= esc($changeLabel) ?>
          </strong>
        </td>
        <td data-label="明細摘要"><?= esc($line['line_description'] ?? '') ?></td>
        <td data-label="金額"><?= esc(number_format((int) $line['amount'])) ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
  <tfoot>
    <tr>
      <th colspan="4">借方合計</th>
      <th><?= esc(number_format($debit)) ?></th>
    </tr>
    <tr>
      <th colspan="4">貸方合計</th>
      <th><?= esc(number_format($credit)) ?></th>
    </tr>
  </tfoot>
</table>

<div class="stack" style="margin-top:12px;">
  <?php if (auth_can('edit')): ?>
    <a class="btn secondary" href="/journal-entries/<?= esc((string) $entry['id']) ?>/edit">編集</a>
  <?php endif; ?>
  <?php if (auth_can('delete')): ?>
    <form method="post" action="/journal-entries/<?= esc((string) $entry['id']) ?>/delete" onsubmit="return confirm('この仕訳を削除しますか？');" style="display:inline;">
      <?= csrf_field() ?>
      <button class="btn" type="submit" style="background:#b91c1c;">削除</button>
    </form>
  <?php endif; ?>
  <a class="btn secondary" href="/journal-entries">一覧へ戻る</a>
</div>
<?= $this->endSection() ?>
