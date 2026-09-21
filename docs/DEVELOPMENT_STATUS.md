# Development Status

## 現在のフェーズ

MVPに向けた機能実装フェーズ。`docs/feature-list.md`のうち「1. ユーザー機能（認証）」「2. お気に入りチーム」「3. 大会・チーム情報」「4. 試合情報」「5. 試合検索（一部）」は実装済み。「6. 店舗機能」以降（店舗・放映・予約）は、**データ層（migration・モデル・Factory・Seeder）まで完成**しており、**画面・Controller・ルートは未着手**。

## 完了済み

いずれも`main`にマージ済み（オープン中のPRはなし。最新は PR #30）。

- **大会・チーム・試合情報のAPI同期**（`SyncCompetitions` / `SyncTeams` / `SyncFootballMatches`）: Football-Data.org APIから取得し`upsert()`でDBへ同期。テスト有り。
- **試合一覧・試合詳細画面**（`MatchController` / `resources/views/matches/index.blade.php` / `show.blade.php`）: 大会・節・ステージによる絞り込み表示。
- **認証機能**（`app/Http/Controllers/Auth/` / `resources/views/auth/`）: ユーザー登録・ログイン・ログアウト。登録時の`users.role`は`user`固定で付与。
- **お気に入りチーム登録機能**（`FavoriteTeamController` / `resources/views/favorite/selectTeams.blade.php`）: 大会をまたいで最大3チームまで登録可能（`sync()`で保存、物理削除方針）。既存登録済みチームはチェック状態で表示される。
- **試合一覧のお気に入り絞り込み・優先表示**: ログイン後、お気に入り登録済みユーザーはデフォルトでチームごとの試合が表示され、「試合一覧」リンクで全体表示に切り替えられる。お気に入り0件のユーザーは常に全体表示。
- **ヘッダーの切り替えリンクのホバースタイル**（PR #25）。
- **`docs/table-definition.md`を実装済みマイグレーションに合わせて修正**（PR #26〜#29）: `shops` / `seat_types` / `broadcasts` / `broadcast_seat_types` / `reservations` / `teams`。`shops.status`と`broadcasts.status`は`draft` / `published`に確定。
- **開発用Factory・Seeder**（PR #30）: `Shop` / `SeatType` / `Broadcast` / `BroadcastSeatType`のFactoryとSeeder、`UserFactory::shopOwner()`、`config/seeder.php`、`DatabaseSeeder`の本番ガード。テスト31件を`tests/Feature/Seeders/`に追加（PR時点で全69件成功、Pint・PHPStan問題なし）。
- **セッション引き継ぎ・開発ルーティン用スラッシュコマンド**: `/next-task`・`/review-pr`・`/style-view`・`/handoff`・`/resume`（`.claude/commands/`配下）。

## 現在実装中

なし。直前のブランチ`feature/shop-list-and-detail-pages`はPR #30（開発用シードデータのみ）でマージ済みで、ローカルの作業ツリーはクリーン。店舗一覧・詳細などの画面実装はまだ始まっていない（ユーザーは、シード完了後に表示・Controller側へ進む意向を示している。着手するタスクの決定は`/next-task`）。

## 重要な技術的判断

CLAUDE.mdに記載済みの恒久ルール（UTC/JST変換、Httpファサード使用、秘密情報管理、migration運用など）は除く、プロジェクト固有の設計判断のみ記載。

- **`favorite_teams`は物理削除方針**（`sync()`を使用）。理由は`docs/table-definition.md`13章に明記済み。
- **`sync()`を使うチェックボックスUIは、既存データを画面に反映してから使う**：初期表示で現在の状態を`@checked()`等で反映しないと、何も操作せず保存するだけで既存データが消える（PR #24で修正した実例）。
- **お気に入りチーム選択画面のみ、JSでの表示切り替えを採用**：大会切り替えのたびにリロードすると選択済みチェック状態が失われるため。他の画面は基本的にフォーム送信＋ページリロード（Ajax不使用）方針。
- **試合カードのマークアップは`resources/views/matches/_card.blade.php`に部分ビュー化**、ステータス表示ロジックは`FootballMatch::statusInfo()`に集約済み。カード表示を追加する画面はこれを再利用する。
- **`MatchController@show`はroute model bindingを使わず`int $id`で実装**（特に不都合が出ていないため現状維持でユーザーと合意済み）。
- **シーダーの設計**（PR #30）:
  - Factoryは1件分のデフォルト値と「基準データから組み立てるstate」を持ち（`BroadcastFactory::forMatch()`、`BroadcastSeatTypeFactory::forSeatType()`、`UserFactory::shopOwner()`）、「どの店にどれを作るか」はSeederが決める。
  - 座席種別は店ごとに種別セットから重複なしで2〜3種類（`UNIQUE(shop_id, name)`対策）。放映は同期済みの**未来の試合**から店ごとに3〜5試合（`UNIQUE(shop_id, football_match_id)`対策）。放映の座席種別は**放映と同じ店舗の有効な座席種別だけ**を紐づけ、座席数・料金は座席種別のデフォルト値を引き継ぐ。
  - `UserFactory`の既定`role`は`user`のまま。店舗オーナーは`shopOwner()`で明示する（既定をランダムにするとテストが不安定になるため）。
- **開発用の管理者アカウント**: `admin@example.com`。パスワードは`.env`の`SEED_ADMIN_PASSWORD`から`config/seeder.php`経由で読み、`null`・空文字なら例外で止める。`.env.example`には値を入れない。`DatabaseSeeder`は本番環境では例外で止まる。

## 未解決の問題

- **シーダーは再実行を前提にしていない**：管理者だけがメールアドレスキーで冪等。Factoryで作る一般ユーザー・店舗オーナーは実行のたびに増え、店舗（`ShopSeeder`）も店舗オーナーごとに増える。`SeatTypeSeeder`は同じ店舗に再実行すると`UNIQUE(shop_id, name)`に当たる可能性がある。
- **（任意対応）`random_int(1, 100) <= 80`が`ShopFactory`・`BroadcastFactory`に残っている**：`fake()->boolean(80)`にすると`fake()->seed()`で結果を再現できる。レビューで任意の指摘としたまま未対応。

## 次にやること

- ローカルの`main`を`git pull`して最新化し、次の作業用ブランチは最新の`main`から切る（ブランチ名`feature/shop-list-and-detail-pages`はPR #30で使用済みで、中身はシードデータのみ）。
- 新しい環境や`.env`を作り直した場合は、`.env`に`SEED_ADMIN_PASSWORD`を設定する。

次に着手する**新機能**の選定は`/next-task`の役割のためここには記載しない。

## 次のセッションへの注意点

- ローカル環境はLaravel Sail（Docker）。Artisanコマンド・テスト・Pint・PHPStanはすべて`./vendor/bin/sail`経由で実行する。ホストで`php artisan test`を実行するとDBホスト名`mysql`を解決できず、大半のテストが接続エラーになる。
- Factoryが有るのは`User` / `Shop` / `SeatType` / `Broadcast` / `BroadcastSeatType`。`Competition` / `Team` / `FootballMatch` / `Reservation`にはFactoryが無い。既存テストは`Model::create([...])`で直接作る慣習で、Seederのテストでは`tests/Concerns/CreatesFootballMatches.php`のヘルパーで試合を作っている。
- **`migrate:fresh`は同期済みの試合（開発DBでは3749件）まで消す**。API同期は大会ごとに`sleep(6)`が入り時間がかかる。シードだけ流し直すときは`db:seed --class=...`を個別に使う。`SyncFootballMatches`は、大会・チームがDBに無い試合を黙ってスキップして「同期に成功しました」と表示するため、先に大会・チームの同期が必要。
- `BroadcastSeeder`は同期済みの未来の試合が前提。0件のときは警告を出して何も作らない。
- 開発DBは2026-09-21時点で、シード済み（`users` 51 / `shops` 10 / `seat_types` 26 / `broadcasts` 41 / `broadcast_seat_types` 108 / `football_matches` 3749 / `reservations` 0）。
- 店舗・放映・予約の画面、Controller、ルートは存在しない。`ReservationController`はresourceの空メソッドが並ぶ雛形のみで、`routes/web.php`にも予約・店舗系のルートは無い。
- `/review-pr`実行時は、作業ブランチが直前にマージ済みの別タスクの名前のまま残っていないか（新しい作業を無関係なブランチ名の上で続けていないか）を確認する。該当する場合は最新の`main`から新しいブランチを切り直す。本題と無関係な問題を見つけた場合も、別ブランチ・別PRに切り出してから本題を続ける運用（例: Pint整形をPR #23、仕様書の修正をPR #26〜#29として切り出した）。
- 作業用featureブランチは、マージ後にローカルで`main`を`pull`しないと、そのブランチの作業ツリーだけ古いファイル内容のままになる（ブランチ自体は`main`の更新を取り込まない）。手元の`main`が古いまま`main`との差分を見ると、すでにマージ済みの変更が「未マージの差分」に見えて誤解しやすい。セッション開始時に`git fetch`し、`main`へ切り替えて`pull`しておく。
- アプリコードの変更は必ずfeatureブランチ上で行う。作業前に`git branch --show-current`で`main`にいないか確認する。
- コミットメッセージ末尾に`Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>`と`Claude-Session:`行を付与する慣習がある。
