<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<h1><?= esc($title) ?></h1>
<p>複式簿記で記帳し、貸借対照表・損益計算書PDFを出力できます。</p>

<div class="grid">
  <div class="card">
    <div>勘定科目</div>
    <strong><?= esc((string) $accountCount) ?> 件</strong>
  </div>
  <div class="card">
    <div>出納帳</div>
    <strong><?= esc((string) ($cashbookCount ?? 0)) ?> 件</strong>
  </div>
  <div class="card">
    <div>会計期間</div>
    <strong><?= esc((string) $periodCount) ?> 件</strong>
  </div>
  <div class="card">
    <div>仕訳件数</div>
    <strong><?= esc((string) $entryCount) ?> 件</strong>
  </div>
</div>

<div class="stack" style="margin-top:16px;">
  <?php if (auth_can('edit')): ?>
    <a class="btn" href="/cashbook/new">出納帳を登録</a>
  <?php endif; ?>
  <a class="btn secondary" href="/cashbook">出納帳を確認</a>
  <?php if (auth_can('edit')): ?>
    <a class="btn" href="/accounts/new">勘定科目を登録</a>
    <a class="btn secondary" href="/fiscal-periods">会計期間を編集</a>
    <a class="btn" href="/journal-entries/new">仕訳を入力</a>
  <?php endif; ?>
  <a class="btn secondary" href="/reports">帳票PDFを出力</a>
  <?php if (auth_can('admin')): ?>
    <a class="btn secondary" href="/users">ユーザー管理</a>
  <?php endif; ?>
</div>
<?= $this->endSection() ?>
