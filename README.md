# Matex (Procurement Workflow Package)

`lastdino/matex` は、Laravel 12 用の調達・在庫管理ワークフローパッケージです。
発注管理、入荷管理、在庫移動、化学物質管理（SDS）などの機能を提供します。

## 主な機能

- **発注管理 (Purchase Orders)**: 発注書の作成、PDF出力、承認フロー連携。
- **入荷管理 (Receiving)**: 発注済商品の入荷処理、検品、在庫への自動反映。
- **在庫管理 (Inventory)**: 現在庫の把握、在庫移動の記録、入出庫履歴。
- **資材管理 (Materials)**: SKU管理、再発注点（最小/最大在庫）設定。
- **化学物質管理**: SDS（安全データシート）の管理、GHSラベル表示、リスクアセスメント連携。
- **サプライヤー管理**: 仕入先情報の管理。
- **設定**: 消費税（期間指定可能）、通貨表示、PDFレイアウト、承認フローIDの設定。

## インストール

### 1. Composer でインストール

```bash
composer require lastdino/matex
```

### 2. マイグレーションの実行

データベーステーブルを作成します。

```bash
php artisan migrate
```

### 3. フロントエンド依存パッケージのインストール

QRコードのスキャン機能を使用するには、`jsqr` をインストールし、ビルドする必要があります。

```bash
npm install jsqr
npm run build
```

### 4. アセットの公開（オプション）

設定ファイルやビューをカスタマイズしたい場合に公開します。

```bash
# 設定ファイルの公開 (config/matex.php)
php artisan vendor:publish --tag=matex-config

# ビューの公開 (resources/views/vendor/matex)
php artisan vendor:publish --tag=matex-views

# 言語ファイルの公開 (lang/vendor/matex)
php artisan vendor:publish --tag=matex-lang
```

## 基本設定

`config/matex.php` にて以下の設定が可能です。

- `route_prefix`: 画面URLのプレフィックス（デフォルト: `matex`）
- `middleware`: 画面アクセスのミドルウェア（デフォルト: `['web', 'auth']`）
- `table_prefix`: データベーステーブルのプレフィックス（デフォルト: `matex_`）
- `api_key`: 外部連携用APIキー
- `monox`: MonoXとの連携設定

## 使い方

インストール後、ブラウザで `/matex`（デフォルト設定の場合）にアクセスするとダッシュボードが表示されます。

### 主なルート

- ダッシュボード: `/matex`
- 発注一覧: `/matex/purchase-orders`
- 入荷待ち一覧: `/matex/pending-receiving`
- 資材管理: `/matex/materials`
- 設定画面: `/matex/settings/options`

## 依存パッケージ

- `laravel/framework` (^12.0)
- `livewire/livewire` (^4.0)
- `livewire/flux` (^2.0)
- `lastdino/approval-flow`: 承認ワークフロー
- `lastdino/chrome-laravel`: PDF生成
- `spatie/laravel-medialibrary`: SDS等のファイル管理
- `jsqr`: QRコードスキャン機能（フロントエンド）

## ライセンス

MIT License.
