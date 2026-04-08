# Project Guidelines

このリポジトリは、CodeIgniter4 で **NPO法人・非営利法人向け会計アプリ**を開発する。  
UIの見た目よりも、**会計整合性・監査性・安全な更新・認証/認可**を優先する。

---

## 1. 目的

- 複式簿記に基づいて日々の記帳を正確に行う。
- 出納帳・仕訳データをもとに B/S・P/L を生成する。
- 必要な帳票を PDF で出力できる構成を維持する。
- 権限ごとに安全に操作範囲を制御する。

---

## 2. 現在の実装範囲

### 実装済み
- 勘定科目マスタ管理
- 会計期間管理
- 出納帳入力・一覧・編集・仕訳転記・PDF出力
- 仕訳入力・一覧・詳細・編集・削除
- 借方合計 = 貸方合計の厳密バリデーション
- OpenAI API を用いた仕訳候補 / 相手勘定候補の自動提案
- **AIに伝えた入力文の保存**と仕訳詳細での表示
- 会計期間ごとの貸借対照表（B/S）・損益計算書（P/L）のPDF出力
- ログイン画面、権限管理、管理者向けユーザー管理
- スマホ閲覧を意識したレスポンシブ表示

### 今後の拡張候補
- 総勘定元帳
- 試算表
- CSV / Excel 取込と一括仕訳化
- 取消仕訳・修正仕訳・締め処理
- 月次集計や関連帳票の拡充

---

## 3. 権限ルール

- **閲覧ユーザー**: 全データ閲覧のみ
- **編集者（制限付き）**: 入力・更新可、削除不可
- **編集者（制限なし）**: 入力・更新・削除可
- **管理者**: 編集者（制限なし）権限 + ユーザー管理

### 実装上の原則
- 権限制御は **UIの非表示だけでなく、必ずルート/Filterでも強制**すること。
- 認証・認可の変更時は、**画面表示・Controller・Route Filter・テスト**をセットで更新すること。
- 管理者が最後の1人になる状態を壊す変更は安全側で制限すること。

---

## 4. アーキテクチャ方針

- CodeIgniter4 の標準構成に従う。
- **Controller は薄く**保ち、HTTP 入出力・バリデーション・リダイレクトに集中させる。
- **Service に業務ロジックを集約**し、仕訳保存、出納帳の仕訳化、貸借一致チェック、AI提案整形、ユーザー管理、トランザクション制御を担わせる。
- **Model は `allowedFields` と timestamps を明示**する。
- View は `app/Views/` の PHP View を使用し、複雑な会計ロジックを書かない。
- 複数テーブル更新は必ず `transStart()` / `transComplete()` を使う。

### 主要ファイル
- `app/Config/Routes.php`: 主要ルートと権限制御
- `app/Config/Filters.php`: `auth` / `permission` フィルタ定義
- `app/Controllers/Auth.php`: ログイン / ログアウト
- `app/Controllers/Users.php`: 管理者向けユーザー管理
- `app/Controllers/Cashbook.php`: 出納帳の一覧・入力・仕訳転記・PDF出力
- `app/Controllers/JournalEntries.php`: 仕訳CRUDとAI提案
- `app/Controllers/Reports.php`: B/S・P/L の表示と PDF 出力
- `app/Services/AuthService.php`: 認証・権限判定
- `app/Services/UserService.php`: ユーザー追加・更新・削除
- `app/Services/CashbookService.php`: 出納帳保存と仕訳生成
- `app/Services/JournalEntryService.php`: 仕訳保存と貸借一致チェック
- `app/Services/JournalEntryAiService.php`: OpenAI 連携と提案結果の正規化
- `app/Services/FinancialStatementService.php`: 財務諸表集計
- `app/Libraries/FinancialStatementPdf.php`: B/S・P/L・出納帳PDF生成
- `app/Database/Seeds/DemoUsersSeeder.php`: 権限別テストユーザー投入

---

## 5. 会計・データ整合性ルール

- すべての仕訳は **借方合計 = 貸方合計** を満たすこと。
- 金額は **整数（円）** で扱い、float を避けること。
- 仕訳ヘッダと明細は、必ず DB トランザクションで同時確定すること。
- 出納帳から生成した仕訳は `journal_entry_id` で関連付け、二重起票や仕訳化後の無断改変を避けること。
- 集計・検索・締め判定では `fiscal_period_id` と `is_closed` を考慮すること。
- AIで提案を受けた場合でも、**最終的な保存データはアプリ側で検証**すること。
- `journal_entries.ai_request_text` には、AIに渡した自然文を保存し、監査可能性を残すこと。

---

## 6. UI / UX の期待

- 日本語UI・日本円運用を前提にする。
- PCだけでなく**スマートフォンからの閲覧・基本操作にも対応**する。
- 表形式UIは必要に応じて **カードリスト形式へ切り替え**、狭い画面でも項目と金額が見やすいようにする。
- 操作ボタンは権限に応じて表示制御するが、**権限制御の本体はサーバー側**に置くこと。

---

## 7. 実装時の期待

- 仕様が曖昧な場合は、会計上の整合性を優先して安全側に実装する。
- `getPost()` の値は文字列前提で `(int)` などの明示キャストを行う。
- 認証・認可・CSRF・入力値検証を維持する。
- 新規機能や修正には、少なくとも正常系・異常系のテストを追加する。
- バグ修正時は、できるだけ回帰テストを先に追加してから直す。

---

## 8. Build / Test / Setup

ローカル確認は Podman を前提とする。

```bash
cd /Users/ringo/Documents/2026/work_CodeIgniter4/example003
podman compose -f podman-compose.yml up --build -d
podman exec -it example003_app php spark migrate
podman exec -it example003_app php spark db:seed AccountingBootstrapSeeder
podman exec -it example003_app php spark db:seed DemoUsersSeeder
podman exec -it example003_app php spark db:seed DemoJournalEntriesSeeder
podman exec -it example003_app php spark db:seed DemoCashbookEntriesSeeder
podman exec example003_app vendor/bin/phpunit --no-coverage
```

- アプリ: `http://localhost:8080`
- ログイン: `http://localhost:8080/login`
- DB: `example003_db`（MariaDB 11）
- AI提案機能を使う場合は `.env` に `openai.apiKey` と `openai.model` を設定する。

---

## 9. 参考資料

- `README.md`: セットアップと現在の実装範囲
- `docs/bash_command.txt`: 日常的な起動・Seed・テストコマンド
- `tests/README.md`: CodeIgniter4 / PHPUnit の基本的な実行方法
- `jupiter729/.github/copilot-instructions.md`: 先行実装の参考資料

---

## 10. 参照方針

- `jupiter729` は移行元・先行実装として扱う。
- 優先して参考にするのは、**ルーティング構成 / Controller と Service の責務分離 / 帳票生成 / 一覧整理**。
- ただし、請求書業務の仕様をそのまま流用せず、**NPO会計の要件と会計整合性を優先**して調整する。

