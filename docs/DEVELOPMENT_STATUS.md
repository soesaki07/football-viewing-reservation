# Development Status

## 現在のフェーズ

MVPに向けた機能実装フェーズ。`docs/feature-list.md`のうち「1. ユーザー機能（認証）」「2. お気に入りチーム」「3. 大会・チーム情報」「4. 試合情報」「5. 試合検索（一部）」まで実装が進んでおり、「6. 店舗機能」以降（店舗・放映・予約）は未着手。

## 完了済み

いずれも`main`にマージ済み。

- **大会・チーム・試合情報のAPI同期**（`app/Console/Commands/SyncCompetitions.php` / `SyncTeams.php` / `SyncFootballMatches.php`）: Football-Data.org APIから取得し`upsert()`でDBへ同期。テスト有り。
- **試合一覧・試合詳細画面**（`MatchController` / `resources/views/matches/index.blade.php` / `show.blade.php`）: 大会・節・ステージによる絞り込み表示。
- **認証機能**（`app/Http/Controllers/Auth/` / `resources/views/auth/`）: ユーザー登録・ログイン・ログアウト。`users.role`は現状`user`固定で付与。
- **お気に入りチーム登録機能**（`FavoriteTeamController` / `resources/views/favorite/selectTeams.blade.php`）: 大会をまたいで最大3チームまで登録可能（`sync()`で保存、物理削除方針）。
- **開発ルーティン用スラッシュコマンド**: `/next-task`・`/review-pr`・`/style-view`（`.claude/commands/`配下）。

## 現在実装中

**PR #21「試合一覧画面へのお気に入りチーム絞り込み機能の実装」**（ブランチ: `feature/favorite-teams`、状態: **Open・未マージ**、レビュー・テスト・Pint・PHPStan・Test全て通過済み、マージ待ち）

内容：`/matches`にログインすると、お気に入り登録済みユーザーはデフォルトでお気に入りチームごとの試合（消化試合2件＋これからの試合3件）が表示され、「試合一覧」リンクで従来の大会/節/ステージ絞り込みの全体表示に切り替えられる。お気に入り0件のユーザーは常に全体表示。

このPRの範囲内でのコードは完成しており、未完成の作業はない。**次のアクションはユーザーによる最終確認とマージ**。

## 重要な技術的判断

CLAUDE.mdに記載済みの恒久ルール（UTC/JST変換、Httpファサード使用、秘密情報管理、migration運用など）は除く、プロジェクト固有の設計判断のみ記載。

- **`favorite_teams`は物理削除方針**（`sync()`を使用）。理由は`docs/table-definition.md`13章に明記済み（過去の事実の記録ではなく現在の状態を表すデータであり、`UNIQUE(user_id, team_id)`制約や`sync()`との相性から）。
- **お気に入りチーム選択画面のみ、JSでの表示切り替えを採用**：大会を切り替えるたびにページ全体をリロードすると選択済みチェック状態が失われるため、全チームを1回のクエリで読み込み、JSで`data-competitions`属性に応じて表示/非表示を切り替える設計にした。他の画面は基本的にフォーム送信＋ページリロード（Ajax不使用）方針を維持。
- **試合カードのマークアップは`resources/views/matches/_card.blade.php`に部分ビュー化**、ステータス表示ロジック（ラベル・色）は`FootballMatch::statusInfo()`としてモデル側に集約済み。今後カード表示を追加する画面があれば、この部分ビューとメソッドを再利用する。
- **`MatchController@show`はroute model bindingを使わず`int $id`で実装**（"問題B"として認識済み、特に不都合が出ていないため現状維持でユーザーと合意済み）。

## 未解決の問題

- `resources/views/layouts/app.blade.php`のヘッダー切り替えリンク（「試合一覧」「お気に入りの試合に戻る」）が、他のナビリンクと同じホバースタイル（`transition hover:text-gold-400`）を引き継げていない。PR #21のレビューで指摘済み・非ブロッキング。`/style-view`での対応を想定。

## 次にやること

- PR #21（お気に入りチーム絞り込み機能）の最終確認・マージ

次に着手する**新機能**の選定は`/next-task`の役割のためここには記載しない。

## 次のセッションへの注意点

- ローカル環境はLaravel Sail（Docker）。Artisanコマンド・テスト・Pint・PHPStanはすべて`./vendor/bin/sail`経由で実行する。
- `Competition` / `Team` / `FootballMatch`にはFactoryが無く、テストでは`Model::create([...])`で直接テストデータを作成する慣習になっている（`User`のみFactory有り）。
- `/review-pr`実行時は、作業ブランチが直前にマージ済みの別タスクの名前のまま残っていないか（＝新しい作業を無関係なブランチ名の上で続けていないか）を確認する習慣がある。該当する場合は最新の`main`から新しいブランチを切り直す。
- コミットメッセージ末尾に`Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>`と`Claude-Session:`行を付与する慣習がある。
