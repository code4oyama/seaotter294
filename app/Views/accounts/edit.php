<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<h1><?= esc($title) ?></h1>
<form method="post" action="/accounts/<?= esc((string) $account['id']) ?>/update">
  <?= csrf_field() ?>
  <label>勘定科目コード</label>
  <input name="code" value="<?= esc(old('code', $account['code'])) ?>" required>

  <label>勘定科目名</label>
  <input name="name" value="<?= esc(old('name', $account['name'])) ?>" required>

  <label>区分</label>
  <select name="category" required>
    <option value="">選択してください</option>
    <?php foreach ($categories as $cat): ?>
      <?php $categoryLabel = $categoryLabels[$cat] ?? $cat; ?>
      <option value="<?= esc($cat) ?>" <?= old('category', $account['category']) === $cat ? 'selected' : '' ?>><?= esc($categoryLabel) ?> (<?= esc($cat) ?>)</option>
    <?php endforeach; ?>
  </select>

  <label style="display:flex;gap:8px;align-items:center; margin-top:12px;">
    <input type="checkbox" name="is_active" value="1" <?= old('is_active', $account['is_active']) ? 'checked' : '' ?> style="width:auto;"> 有効
  </label>

  <div class="stack" style="margin-top:12px;">
    <button class="btn" type="submit">更新</button>
    <a class="btn secondary" href="/accounts">戻る</a>
  </div>
</form>
<?= $this->endSection() ?>
