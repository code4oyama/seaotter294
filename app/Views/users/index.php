<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<h1><?= esc($title) ?></h1>
<div class="stack">
  <a class="btn" href="/users/new">ユーザー追加</a>
</div>

<table class="responsive-table">
  <thead>
    <tr>
      <th>表示名</th>
      <th>メールアドレス</th>
      <th>権限</th>
      <th>状態</th>
      <th>最終ログイン</th>
      <th>操作</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($users as $user): ?>
      <tr>
        <td data-label="表示名"><?= esc($user['name']) ?></td>
        <td data-label="メールアドレス"><?= esc($user['email']) ?></td>
        <td data-label="権限"><?= esc($roleLabels[$user['role']] ?? $user['role']) ?></td>
        <td data-label="状態"><?= ! empty($user['is_active']) ? '有効' : '無効' ?></td>
        <td data-label="最終ログイン"><?= esc($user['last_login_at'] ?? '未ログイン') ?></td>
        <td data-label="操作">
          <div class="stack">
            <a class="btn secondary" href="/users/<?= esc((string) $user['id']) ?>/edit">編集</a>
            <form method="post" action="/users/<?= esc((string) $user['id']) ?>/delete" onsubmit="return confirm('このユーザーを削除しますか？');" style="display:inline;">
              <?= csrf_field() ?>
              <button class="btn" type="submit" style="background:#b91c1c;">削除</button>
            </form>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?= $this->endSection() ?>
