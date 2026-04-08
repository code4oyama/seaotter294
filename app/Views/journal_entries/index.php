<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<h1><?= esc($title) ?></h1>
<?php if (auth_can('edit')): ?>
  <div class="stack">
    <a class="btn" href="/journal-entries/new">新規仕訳</a>
  </div>
<?php endif; ?>
<table class="responsive-table">
  <thead>
    <tr>
      <th>日付</th>
      <th>伝票番号</th>
      <th>会計期間</th>
      <th>摘要</th>
      <th>操作</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($entries as $entry): ?>
      <tr>
        <td data-label="日付"><?= esc($entry['entry_date']) ?></td>
        <td data-label="伝票番号"><?= esc($entry['voucher_number']) ?></td>
        <td data-label="会計期間"><?= esc($entry['fiscal_period_name'] ?? '') ?></td>
        <td data-label="摘要"><?= esc($entry['description']) ?></td>
        <td data-label="操作">
          <div class="stack">
            <a class="btn" href="/journal-entries/<?= esc((string) $entry['id']) ?>">詳細</a>
            <?php if (auth_can('edit')): ?>
              <a class="btn secondary" href="/journal-entries/<?= esc((string) $entry['id']) ?>/edit">編集</a>
            <?php endif; ?>
            <?php if (auth_can('delete')): ?>
              <form method="post" action="/journal-entries/<?= esc((string) $entry['id']) ?>/delete" onsubmit="return confirm('この仕訳を削除しますか？');" style="display:inline;">
                <?= csrf_field() ?>
                <button class="btn" type="submit" style="background:#b91c1c;">削除</button>
              </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?= $this->endSection() ?>
