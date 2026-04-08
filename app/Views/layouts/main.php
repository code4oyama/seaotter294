<!doctype html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= esc($title ?? 'NPO会計アプリ') ?></title>
  <style>
    body { font-family: "Hiragino Kaku Gothic ProN", "Yu Gothic", sans-serif; margin: 0; background: #f7f8fa; color: #222; line-height: 1.6; }
    header { background: #124559; color: #fff; padding: 14px 20px; }
    nav { display: flex; flex-wrap: wrap; gap: 8px 14px; }
    nav a { color: #fff; text-decoration: none; font-weight: 600; }
    main { max-width: 1000px; margin: 24px auto; background: #fff; border-radius: 10px; padding: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); }
    h1 { margin-top: 0; font-size: 1.4rem; }
    table { width: 100%; border-collapse: collapse; margin-top: 12px; }
    th, td { border-bottom: 1px solid #e5e7eb; padding: 10px 8px; text-align: left; vertical-align: top; }
    .btn { display: inline-block; background: #167288; color: #fff; text-decoration: none; border: none; border-radius: 8px; padding: 8px 12px; cursor: pointer; }
    .btn.secondary { background: #4b5563; }
    .stack { display: flex; gap: 8px; flex-wrap: wrap; }
    .flash { padding: 10px 12px; border-radius: 8px; margin-bottom: 12px; }
    .flash.ok { background: #ecfdf3; color: #166534; }
    .flash.err { background: #fef2f2; color: #991b1b; }
    .grid { display: grid; grid-template-columns: repeat(auto-fit,minmax(180px,1fr)); gap: 14px; }
    .card { background: #f8fafc; border: 1px solid #dbeafe; border-radius: 10px; padding: 14px; }
    label { display: block; font-size: 0.9rem; margin-top: 10px; margin-bottom: 6px; }
    input, select, textarea { width: 100%; box-sizing: border-box; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px; }
    .detail-table th { width: 180px; background: #f8fafc; }

    @media (max-width: 768px) {
      header { padding: 12px 14px; }
      main { margin: 12px; padding: 14px; border-radius: 8px; }
      .stack { flex-direction: column; align-items: stretch !important; }
      .stack > * { width: 100%; box-sizing: border-box; }
      .stack form .btn,
      .stack a.btn,
      .stack button.btn { width: 100%; text-align: center; }

      .responsive-table thead {
        display: none;
      }

      .responsive-table,
      .responsive-table tbody,
      .responsive-table tr,
      .responsive-table td,
      .responsive-table th {
        display: block;
        width: 100%;
        box-sizing: border-box;
      }

      .responsive-table tr {
        margin-top: 12px;
        padding: 12px;
        border: 1px solid #dbeafe;
        border-radius: 10px;
        background: #f8fafc;
      }

      .responsive-table td {
        display: grid;
        grid-template-columns: minmax(88px, 104px) 1fr;
        gap: 8px;
        border-bottom: 1px dashed #dbeafe;
        padding: 8px 0;
      }

      .responsive-table td:last-child {
        border-bottom: 0;
      }

      .responsive-table td::before {
        content: attr(data-label);
        font-weight: 700;
        color: #475569;
      }

      .responsive-table td[data-label="操作"] {
        display: block;
      }

      .responsive-table td[data-label="操作"]::before {
        display: block;
        margin-bottom: 6px;
      }

      .responsive-table tfoot,
      .responsive-table tfoot tr,
      .responsive-table tfoot th,
      .responsive-table tfoot td {
        display: block;
        width: 100%;
        box-sizing: border-box;
      }

      .responsive-table tfoot tr {
        margin-top: 8px;
        padding: 8px 0 0;
        border: 0;
        background: transparent;
      }

      .responsive-table tfoot th,
      .responsive-table tfoot td {
        border: 0;
        padding: 2px 0;
      }

      .responsive-table.line-entry-table td {
        grid-template-columns: 1fr;
      }

      .responsive-table.line-entry-table td::before {
        margin-bottom: 4px;
      }

      .detail-table,
      .detail-table tbody,
      .detail-table tr,
      .detail-table th,
      .detail-table td {
        display: block;
        width: 100%;
        box-sizing: border-box;
      }

      .detail-table tr {
        margin-top: 12px;
        padding: 12px;
        border: 1px solid #dbeafe;
        border-radius: 10px;
        background: #f8fafc;
      }

      .detail-table th,
      .detail-table td {
        border: 0;
        padding: 0;
      }

      .detail-table th {
        margin-bottom: 4px;
        font-size: 0.92rem;
        color: #475569;
        background: transparent;
      }

      .detail-table td {
        word-break: break-word;
      }
    }
  </style>
</head>
<body>
<header>
  <strong>NPO会計アプリ</strong>
  <?php if (auth_is_logged_in()): ?>
    <nav style="margin-top:8px;">
      <a href="/">ダッシュボード</a>
      <a href="/cashbook">出納帳</a>
      <a href="/accounts">勘定科目</a>
      <a href="/fiscal-periods">会計期間</a>
      <a href="/journal-entries">仕訳一覧</a>
      <?php if (auth_can('edit')): ?>
        <a href="/journal-entries/new">仕訳入力</a>
      <?php endif; ?>
      <a href="/reports">帳票PDF</a>
      <?php if (auth_can('admin')): ?>
        <a href="/users">ユーザー管理</a>
      <?php endif; ?>
    </nav>
    <div style="margin-top:8px; display:flex; gap:12px; flex-wrap:wrap; align-items:center; font-size:0.92rem;">
      <span>ログイン中: <?= esc((string) (auth_user()['name'] ?? '')) ?>（<?= esc(auth_role_label()) ?>）</span>
      <form method="post" action="/logout" style="margin:0;">
        <?= csrf_field() ?>
        <button class="btn secondary" type="submit">ログアウト</button>
      </form>
    </div>
  <?php else: ?>
    <nav style="margin-top:8px;">
      <a href="/login">ログイン</a>
    </nav>
  <?php endif; ?>
</header>
<main>
  <?php if (session()->getFlashdata('message')): ?>
    <div class="flash ok"><?= esc(session()->getFlashdata('message')) ?></div>
  <?php endif; ?>
  <?php if (session()->getFlashdata('error')): ?>
    <div class="flash err"><?= esc(session()->getFlashdata('error')) ?></div>
  <?php endif; ?>

  <?= $this->renderSection('content') ?>
</main>
</body>
</html>
