# NPO会計アプリ (CodeIgniter4)

NPO法人・非営利法人向けの会計アプリです。  
**複式簿記の整合性・監査性・安全な更新**を優先し、出納帳から仕訳、B/S・P/L・PDF出力までを扱います。

## プロジェクト開始日時

2026-03-23 月曜日 01:23:25

---

## 概要

このアプリでは、以下の業務を一通り行えます。

- 勘定科目マスタ管理
- 会計期間管理
- 出納帳の入力・一覧・仕訳転記
- 仕訳の入力・一覧・詳細・編集・削除
- AI提案による仕訳候補・相手科目候補の補助
- 貸借対照表（B/S）・損益計算書（P/L）・出納帳のPDF出力
- ログイン認証と権限管理
- スマートフォンでも見やすいレスポンシブ表示

---

## 権限の種類

| 権限 | できること |
|---|---|
| 閲覧ユーザー | 全データの閲覧のみ |
| 編集者（制限付き） | 入力・更新は可能、削除は不可 |
| 編集者（制限なし） | 入力・更新・削除が可能 |
| 管理者 | 編集者（制限なし）の権限 + ユーザー管理 |

### デモサイト
https://seaotter294.frog256.org/

### テスト用アカウント
共通パスワード: `password123`

| 権限 | アカウント1 | アカウント2 |
|---|---|---|
| 閲覧ユーザー | `viewer01@example.test` | `viewer02@example.test` |
| 編集者（制限付き） | `limited01@example.test` | `limited02@example.test` |
| 編集者（制限なし） | `editor01@example.test` | `editor02@example.test` |
| 管理者 | `admin01@example.test` | `admin02@example.test` |

---

## ローカルセットアップ（Podman）

```bash
cd /Users/ringo/Documents/2026/work_CodeIgniter4/example003
podman-compose up --build -d
podman-compose exec app php spark migrate
podman-compose exec app php spark db:seed AccountingBootstrapSeeder
podman-compose exec app php spark db:seed DemoUsersSeeder
podman-compose exec app php spark db:seed DemoJournalEntriesSeeder
podman-compose exec app php spark db:seed DemoCashbookEntriesSeeder
```

### アクセス先
- アプリ: `http://localhost:8080`
- ログイン画面: `http://localhost:8080/login`

> 補足: よく使うコマンドは `docs/bash_command.txt` に整理しています。

---

## AI提案機能の設定

`.env` に以下を設定してください。

```dotenv
openai.apiKey = sk-xxxx
openai.model = gpt-4o-mini
```

- `openai.apiKey`: OpenAI APIキー
- `openai.model`: 利用モデル名（未設定時は `gpt-4o-mini`）

### AI関連の現在仕様
- 仕訳入力で自然文から借方・貸方候補を提案
- 出納帳入力で相手科目候補を提案
- **AIに伝えた入力文はDBに保存**し、仕訳詳細画面で再確認可能

---

## 主な画面

- ダッシュボード: `/`
- ログイン: `/login`
- ユーザー管理: `/users`（管理者のみ）
- 出納帳: `/cashbook`
- 勘定科目一覧: `/accounts`
- 会計期間一覧: `/fiscal-periods`
- 仕訳一覧: `/journal-entries`
- 帳票PDF: `/reports`

---

## テスト実行

```bash
# 全体テスト
podman-compose exec app vendor/bin/phpunit --no-coverage

# 認証・権限まわり
podman-compose exec app vendor/bin/phpunit --no-coverage tests/feature/Step2/AuthFeatureTest.php

# 出納帳まわり
podman-compose exec app vendor/bin/phpunit --no-coverage tests/feature/Step2/CashbookFeatureTest.php
```

カバレッジを出す場合:

```bash
podman-compose exec app vendor/bin/phpunit --coverage-html coverage
```

---

## GitHub Actions

このリポジトリには、以下の workflow を用意しています。

| ファイル | 用途 |
|---|---|
| `.github/workflows/ci.yml` | PHPUnit とカバレッジを GitHub Actions 上で実行 |
| `.github/workflows/deploy-sakura.yml` | `main` 成功後、または手動実行でさくらサーバーへデプロイ |

### `ci.yml`
- `push` / `pull_request` / `workflow_dispatch` で実行
- PHP 8.2 をセットアップ
- Composer install 後に PHPUnit を実行
- `build/logs/` を artifact として保存

### `deploy-sakura.yml`
- `CI` 成功後の `main` push を契機にデプロイ
- `workflow_dispatch` から手動実行も可能
- 必要に応じて `php spark migrate` を実行
- 初期セットアップ時のみ `AccountingBootstrapSeeder` 実行も可能

### デプロイに必要な Secrets
さくらインターネットの**レンタルサーバー**へ CodeIgniter4 アプリを配置する想定では、以下の値を設定します。

- `SAKURA_SSH_PRIVATE_KEY`  
  GitHub Actions から SSH 接続するための秘密鍵。パスキーなしのオプションで鍵を生成する。
  例: ローカルで作成した `id_ed25519` の秘密鍵ファイルの本文

- `SAKURA_HOST`  
  さくらの SSH 接続先ホスト名。  
  例: `sakura_rental_server_account.sakura.ne.jp`

- `SAKURA_PORT`  
  SSH の接続ポート。通常は `22`。  
  例: `22`

- `SAKURA_USER`  
  さくらレンタルサーバーの SSH ログインユーザー名。  
  例: `sakura_rental_server_account`

- `SAKURA_APP_DIR`  
  **CodeIgniter4 本体一式**（`app/`, `writable/`, `vendor/` など）を置くディレクトリ。  
  `public/` の外側に置く前提です。  
  例: `/home/sakura_rental_server_account/www/seaotter294`

- `SAKURA_WEB_DIR`  
  **Web公開ディレクトリ**。CodeIgniter4 の `public/` の中身を同期する先です。  
  さくらでは通常 `www/` 配下を公開領域にします。  
  例: `/home/sakura_rental_server_account/www/seaotter294/public`

> 補足: サーバー側の DocumentRoot を `public/` にできない場合でも、リポジトリ直下の `.htaccess` で `/public` へ転送するフォールバックを用意しています。ただし、**最優先は DocumentRoot を `public/` に合わせること**です。

任意:
- `SAKURA_DOTENV`  
  本番用 `.env` の中身をそのまま Secret に入れておく用途。  
  DB接続先、`app.baseURL`、`openai.apiKey` などを含めます。  
  **`app.baseURL` は本番ドメインに必ず合わせてください。** 例:
  ```dotenv
  CI_ENVIRONMENT = production
  app.baseURL = 'https://seaotter.frog256.org/'
  ```

> 推奨構成: `public/` の内容だけを `SAKURA_WEB_DIR` に公開し、アプリ本体は `SAKURA_APP_DIR` に置くと、CodeIgniter4 の標準的な安全構成にしやすいです。

---

## 今後の拡張候補

- 総勘定元帳
- 試算表
- CSV / Excel 取込
- 取消仕訳・修正仕訳
- 締め処理・月次集計の強化

---

## ライセンス

本アプリは「独自ライセンス」に基づき公開しています。

### ライセンス内容

- 利用・複製・改変：自由に行えます。
- 配布・再配布：禁止します。
- 商用利用：禁止します。
- 著作権表示：本アプリの著作権表示を削除しないでください。
- 無保証：本アプリの利用によるいかなる損害も、作者は一切責任を負いません。

#### 詳細説明

本アプリのソースコードおよび成果物は、個人または組織内での利用・複製・改変は許可しますが、
第三者への配布や再配布、または商用目的での利用・販売・公開は禁止します。
また、著作権表示および本ライセンス文は削除せず、必ず残してください。

ご不明点があれば、リポジトリ管理者までご連絡ください。

## 貢献

プルリクエストを歓迎します。大きな変更の場合は、まずissueを開いて変更内容を議論してください。


AAA
AAA
AAA
AAA
AAA
AAA




