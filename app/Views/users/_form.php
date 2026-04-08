<?php
$user = $user ?? [];
$isEdit = $user !== [];
?>
<form method="post" action="<?= esc($action) ?>">
  <?= csrf_field() ?>

  <label>表示名</label>
  <input name="name" value="<?= esc(old('name', $user['name'] ?? '')) ?>" required>

  <label>メールアドレス</label>
  <input type="email" name="email" value="<?= esc(old('email', $user['email'] ?? '')) ?>" required>

  <label>権限</label>
  <select name="role" required>
    <option value="">選択してください</option>
    <?php foreach ($roles as $value => $label): ?>
      <option value="<?= esc($value) ?>" <?= old('role', $user['role'] ?? '') === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
    <?php endforeach; ?>
  </select>

  <label>パスワード<?= $isEdit ? '（変更時のみ入力）' : '' ?></label>
  <input type="password" name="password" placeholder="8文字以上" <?= $isEdit ? '' : 'required' ?>>

  <label style="display:flex; align-items:center; gap:8px; margin-top:14px;">
    <input type="checkbox" name="is_active" value="1" <?= old('is_active', (string) ($user['is_active'] ?? '1')) ? 'checked' : '' ?> style="width:auto;">
    有効ユーザー
  </label>

  <div class="stack" style="margin-top:16px;">
    <button class="btn" type="submit"><?= $isEdit ? '更新' : '追加' ?></button>
    <a class="btn secondary" href="/users">戻る</a>
  </div>
</form>
