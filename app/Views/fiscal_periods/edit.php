<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<h1><?= esc($title) ?></h1>
<p>会計期間名と開始日・終了日を更新できます。締め状態の変更はまだ実装していません。</p>

<form method="post" action="/fiscal-periods/<?= esc((string) $period['id']) ?>/update">
  <?= csrf_field() ?>

  <label>会計期間名</label>
  <input name="name" value="<?= esc(old('name', $period['name'])) ?>" required>

  <label>開始日</label>
  <input type="date" name="start_date" value="<?= esc(old('start_date', $period['start_date'])) ?>" required>

  <label>終了日</label>
  <input type="date" name="end_date" value="<?= esc(old('end_date', $period['end_date'])) ?>" required>

  <div class="card" style="margin-top:12px; background:#f8fafc; border-color:#dbeafe;">
    現在の状態: <strong><?= ! empty($period['is_closed']) ? '締め済み' : '未締め' ?></strong><br>
    <span style="font-size:0.92rem; color:#475569;">※ 締め処理の変更機能はまだ実装していません。</span>
  </div>

  <div class="stack" style="margin-top:12px;">
    <button class="btn" type="submit">更新</button>
    <a class="btn secondary" href="/fiscal-periods">戻る</a>
  </div>
</form>
<?= $this->endSection() ?>
