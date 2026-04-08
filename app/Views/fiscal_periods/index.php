<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<h1><?= esc($title) ?></h1>
<p>会計期間の名称と対象日付を編集できます。締め処理はまだ未実装です。</p>

<?php if ($periods === []): ?>
  <div class="card">会計期間がまだ登録されていません。</div>
<?php else: ?>
  <table class="responsive-table">
    <thead>
      <tr>
        <th>名称</th>
        <th>開始日</th>
        <th>終了日</th>
        <th>状態</th>
        <th>操作</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($periods as $period): ?>
        <tr>
          <td data-label="名称"><?= esc($period['name']) ?></td>
          <td data-label="開始日"><?= esc($period['start_date']) ?></td>
          <td data-label="終了日"><?= esc($period['end_date']) ?></td>
          <td data-label="状態"><?= ! empty($period['is_closed']) ? '締め済み' : '未締め' ?></td>
          <td data-label="操作">
            <?php if (auth_can('edit')): ?>
              <a class="btn secondary" href="/fiscal-periods/<?= esc((string) $period['id']) ?>/edit">編集</a>
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
