<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<h1><?= esc($title) ?></h1>
<p>会計期間ごとに、貸借対照表（B/S）と損益計算書（P/L）のPDFを出力できます。</p>

<?php if ($periods === []): ?>
  <div class="card">会計期間がまだありません。</div>
<?php else: ?>
  <table class="responsive-table">
    <thead>
      <tr>
        <th>会計期間</th>
        <th>対象日付</th>
        <th>仕訳件数</th>
        <th>PDF出力</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($periods as $period): ?>
        <tr>
          <td data-label="会計期間"><?= esc($period['name']) ?></td>
          <td data-label="対象日付"><?= esc($period['start_date']) ?> 〜 <?= esc($period['end_date']) ?></td>
          <td data-label="仕訳件数"><?= esc((string) ($period['entry_count'] ?? 0)) ?> 件</td>
          <td data-label="PDF出力">
            <div class="stack">
              <a class="btn" href="/reports/balance-sheet/<?= esc((string) $period['id']) ?>/pdf" target="_blank" rel="noopener">貸借対照表PDF</a>
              <a class="btn secondary" href="/reports/profit-loss/<?= esc((string) $period['id']) ?>/pdf" target="_blank" rel="noopener">損益計算書PDF</a>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
<?= $this->endSection() ?>
