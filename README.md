Laravel 向け Procurement Flow

概要

このパッケージは、調達（Procurement）業務のための Livewire ベース UI、ルーティング、設定、翻訳、PDF 生成や受入スキャン（QR）などのワークフローを提供する Laravel パッケージです。アプリケーションに組み込むことで、下記の機能をすぐに利用できます。

- ダッシュボード / 発注一覧・詳細 / 仕入先一覧
- 資材（Materials）一覧・詳細・払出（Issue）画面、SDS ダウンロード
- 受入（Receiving）スキャン、発注作成用の注文スキャン（Ordering）
- 調達関連の設定（オプション・承認フロー・税・PDF・カテゴリ・トークン／ラベル）
- 画面、翻訳（日本語/英語）の公開・上書き

機能一覧

- ダッシュボード、発注（一覧・詳細・PDF）、仕入先一覧
- 資材：一覧・詳細・払出、SDS の署名付きダウンロード
- 受入：QR スキャンで入庫登録／注文：QR スキャンでドラフト発注作成
- 設定画面：オプション、承認フロー、税、PDF、カテゴリ、トークン、ラベル
- ビュー／翻訳の名前空間、設定・言語ファイルの公開

動作要件

- PHP: ^8.4
- Laravel Framework: ^12.0
- Livewire: v3
- Tailwind CSS: v4（アプリ側でのビルドが必要）
- 推奨: Laravel Herd によるローカル提供（例: http://procuraflow.test）
- Composer により以下を自動インストール：
  - lastdino/approval-flow ^0.1
  - tuncaybahadir/quar ^1.7
  - lastdino/chrome-laravel ^0.1
  - spatie/laravel-medialibrary ^11.0

インストール

1) Composer でインストール

```
composer require lastdino/procurement-flow
```

Monorepo（`packages/lastdino/procurement-flow`）での開発中は、ルートの composer がパッケージをロードできるように設定してください（`path` リポジトリなど）。

2) サービスプロバイダ（自動検出）

```
Lastdino\ProcurementFlow\ProcurementFlowServiceProvider
```

3) 設定と翻訳の公開（任意）

```
php artisan vendor:publish --tag=procurement-flow-config --no-interaction
php artisan vendor:publish --tag=procurement-flow-lang --no-interaction
```

セットアップの注意

- APP_URL を Herd の URL に合わせて設定してください（例: `APP_URL=http://procuraflow.test`）。
- Vite / Tailwind v4 を利用するため、UI を変更した場合は `npm run dev` または `npm run build` を実行してください。
- Livewire v3 を使用しています。アプリ側で Livewire のセットアップが済んでいることを前提とします。

実行・ビルド

- アプリ側で必要なコマンド例：

```
# PHP 依存関係
composer install

# 開発用ビルド
npm install
npm run dev

# 本番ビルド
npm run build
```

ルートと主要 URL

本パッケージの Web ルートは、設定されたプレフィックスとミドルウェアでグルーピングされます。既定のプレフィックスは `/procurement` です。Herd の既定 URL 例：`http://procuraflow.test/procurement`。

Named routes（抜粋）:

- `procurement.dashboard` → `/`
  - 例: http://procuraflow.test/procurement
- Purchase Orders:
  - `procurement.purchase-orders.index` → `/purchase-orders`
    - 例: http://procuraflow.test/procurement/purchase-orders
  - `procurement.purchase-orders.show` → `/purchase-orders/{po}`
  - `procurement.purchase-orders.pdf` → `/purchase-orders/{po}/pdf`
- Pending Receiving:
  - `procurement.pending-receiving.index` → `/pending-receiving`
- Materials:
  - `procurement.materials.index` → `/materials`
  - `procurement.materials.show` → `/materials/{material}`
  - `procurement.materials.issue` → `/materials/{material}/issue`
  - `procurement.materials.sds.download`（署名付き）→ `/materials/{material}/sds`
- Suppliers:
  - `procurement.suppliers.index` → `/suppliers`
- Receiving Scan:
  - `procurement.receiving.scan` → `/receivings/scan`
  - `procurement.receiving.scan.info` → `/receivings/scan/info/{token}`
  - `procurement.receiving.scan.receive` → `/receivings/scan/receive`
- Settings:
  - `procurement.settings.options` → `/settings/options`
  - `procurement.settings.approval` → `/settings/approval`
  - `procurement.settings.taxes` → `/settings/taxes`
  - `procurement.settings.pdf` → `/settings/pdf`
  - `procurement.settings.categories` → `/settings/categories`
  - `procurement.settings.tokens` → `/settings/tokens`
  - `procurement.settings.labels` → `/settings/labels`
- Ordering Scan:
  - `procurement.ordering.scan` → `/ordering/scan`

ビューと翻訳の名前空間

- Views: namespace `procflow`（例: `procflow::livewire.procurement.materials.index`）
- Translations: namespace `procflow`（例: `__('procflow::materials.table.name')`）

Livewire コンポーネント（登録済み）

以下はサービスプロバイダにより登録され、画面やルートで参照されます：

- `procurement.dashboard`
- `purchase-orders.index`, `purchase-orders.show`
- `suppliers.index`
- `procurement.materials`, `procurement.materials.issue`
- `procurement.pending-receiving.index`
- `procurement.receiving.scan`
- `procurement.ordering.scan`
- Settings:
  - `procurement.settings.options.index`
  - `procurement.settings.approval.index`
  - `procurement.settings.taxes.index`
  - `procurement.settings.pdf.index`
  - `procurement.settings.categories.index`
  - `procurement.settings.tokens.index`
  - `procurement.settings.tokens.labels`

使い方・設定例

設定キー / ファイル

- 設定キー: `procurement_flow`
- 公開先: `config/procurement-flow.php`

主なオプション（抜粋）

- `route_prefix`: UI の URL プレフィックス（既定: `procurement`）
- `middleware`: UI に適用するミドルウェア（既定: `['web', 'auth']`）
- `enabled`: 機能の有効/無効フラグ
- `ghs`:
  - `disk`: GHS ピクトグラム画像の保存ディスク名（例: `public`）
  - `directory`: ディスク直下の保存ディレクトリ（例: `ghs_labels`）
  - `map`: GHS キーとファイル名の対応（例: `GHS01 => GHS01.bmp`）
  - `placeholder`: 未定義／欠損時のプレースホルダ（`null` で非表示）

GHS 設定例

```php
// config/procurement-flow.php（抜粋）
return [
    'ghs' => [
        'disk' => 'public',
        'directory' => 'ghs_labels',
        'map' => [
            'GHS01' => 'GHS01.png',
            'GHS02' => 'GHS02.png',
            // ...
        ],
        'placeholder' => 'placeholder.png',
    ],
];
```

SDS（安全データシート）

- Material モデルの Media Library コレクション `sds` に SDS ファイルを登録してください。
- ダウンロードは署名付きかつ認証済みルート `procurement.materials.sds.download` で提供されます。

PDF（発注書）

- `lastdino/chrome-laravel` を用いて PDF を生成します。Chrome / Chromium の実行環境をアプリ側で用意してください。
- レイアウトやロゴ等はアプリ側で上書き可能です（ビュー公開・上書きを活用）。

QR / スキャン

- 受入（Receiving）および注文（Ordering）のスキャン用に JSON API と Livewire 画面を提供します。
- 権限はグループミドルウェア（既定は `web` + `auth`）に従います。

カスタマイズ

- 画面・翻訳の公開と上書き
- ルートプレフィックス・ミドルウェアの変更
- GHS 画像の差し替え（ストレージ設定）

ローカル開発（Monorepo）

- パス: `packages/lastdino/procurement-flow`
- プロバイダ: `Lastdino\ProcurementFlow\ProcurementFlowServiceProvider`
- UI 変更が反映されない場合は `npm run dev` または `npm run build` を実行してください。

ライセンス

MIT License.
