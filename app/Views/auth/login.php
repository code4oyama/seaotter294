<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<h1><?= esc($title) ?></h1>
<p>メールアドレスとパスワードでログインしてください。</p>

<div class="card" style="max-width:520px; margin:16px auto 0;">
  <form method="post" action="/login">
    <?= csrf_field() ?>

    <label>メールアドレス</label>
    <input type="email" name="login" value="<?= esc(old('login')) ?>" placeholder="example@example.test" required>

    <label>パスワード</label>
    <input type="password" name="password" placeholder="8文字以上" required>

    <div class="stack" style="margin-top:16px;">
      <button class="btn" type="submit">ログイン</button>
    </div>
  </form>
</div>

<div class="card" style="margin-top:16px; background:#f8fafc;">
  <strong>テスト用ユーザー</strong>
  <ul style="margin:8px 0 0 18px; padding:0;">
    <li>閲覧: `viewer01@example.test` / `password123`</li>
    <li>制限付き編集: `limited01@example.test` / `password123`</li>
    <li>制限なし編集: `editor01@example.test` / `password123`</li>
    <li>管理者: `admin01@example.test` / `password123`</li>
  </ul>
</div>
<?= $this->endSection() ?>
