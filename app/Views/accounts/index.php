<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<h1><?= esc($title) ?></h1>
<?php if (auth_can('edit')): ?>
  <div class="stack">
    <a class="btn" href="/accounts/new">新規登録</a>
  </div>
<?php endif; ?>
<table class="responsive-table">
  <thead>
    <tr>
      <th>コード</th>
      <th>名称</th>
      <th>区分</th>
      <th>有効</th>
      <th>操作</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($accounts as $account): ?>
      <?php $categoryLabel = $categoryLabels[$account['category']] ?? $account['category']; ?>
      <tr>
        <td data-label="コード"><?= esc($account['code']) ?></td>
        <td data-label="名称"><?= esc($account['name']) ?></td>
        <td data-label="区分">
          <?= esc($categoryLabel) ?>
          <?php if ($categoryLabel !== $account['category']): ?>
            <span style="color:#64748b;">(<?= esc($account['category']) ?>)</span>
          <?php endif; ?>
        </td>
        <td data-label="有効"><?= $account['is_active'] ? 'はい' : 'いいえ' ?></td>
        <td data-label="操作">
          <?php if (auth_can('edit')): ?>
            <div class="stack">
              <a class="btn secondary" href="/accounts/<?= esc((string) $account['id']) ?>/edit">編集</a>
              <?php if (auth_can('delete')): ?>
                <form method="post" action="/accounts/<?= esc((string) $account['id']) ?>/delete" onsubmit="return confirm('この勘定科目を削除しますか？');" style="display:inline;">
                  <?= csrf_field() ?>
                  <button class="btn" type="submit" style="background:#b91c1c;">削除</button>
                </form>
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
<?= $this->endSection() ?>
