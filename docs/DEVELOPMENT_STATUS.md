# Development Status

## 現在のフェーズ

MVPに向けた機能実装フェーズ。`docs/feature-list.md`のうち「1. ユーザー機能（認証）」「2. お気に入りチーム」「3. 大会・チーム情報」「4. 試合情報」「5. 試合検索（一部）」まで実装が進んでおり、「6. 店舗機能」以降（店舗・放映・予約）は未着手。

## 完了済み

いずれも`main`にマージ済み（オープン中のPRはなし）。

- **大会・チーム・試合情報のAPI同期**（`SyncCompetitions` / `SyncTeams` / `SyncFootballMatches`）: Football-Data.org APIから取得し`upsert()`でDBへ同期。テスト有り。
- **試合一覧・試合詳細画面**（`MatchController` / `resources/views/matches/index.blade.php` / `show.blade.php`）: 大会・節・ステージによる絞り込み表示。
- **認証機能**（`app/Http/Controllers/Auth/` / `resources/views/auth/`）: ユーザー登録・ログイン・ログアウト。`users.role`は現状`user`固定で付与。
- **お気に入りチーム登録機能**（`FavoriteTeamController` / `resources/views/favorite/selectTeams.blade.php`）: 大会をまたいで最大3チームまで登録可能（`sync()`で保存、物理削除方針）。既存登録済みチームはチェック状態で表示される（PR #24で修正済み）。
- **試合一覧のお気に入り絞り込み・優先表示**（PR #21）: `/matches`にログインすると、お気に入り登録済みユーザーはデフォルトでチームごとの試合（消化試合2件＋これからの試合3件）が表示され、「試合一覧」リンクで従来の全体表示に切り替えられる。お気に入り0件のユーザーは常に全体表示。
- **セッション引き継ぎの仕組み**（PR #22）: `docs/DEVELOPMENT_STATUS.md`・`/handoff`・`/resume`を追加。
- **開発ルーティン用スラッシュコマンド**: `/next-task`・`/review-pr`・`/style-view`・`/handoff`・`/resume`（`.claude/commands/`配下）。
## 現在実装中

**ヘッダーの切り替えリンクのホバースタイル修正**（ブランチ: `chore/header-hover-style-fix`、`main`から分岐後に作成。PR作成予定）。`resources/views/layouts/app.blade.php`の修正と、あわせて`docs/DEVELOPMENT_STATUS.md`の更新を含む。Pint/PHPStan/Test確認済み、未完成の作業はない。

## 重要な技術的判断

CLAUDE.mdに記載済みの恒久ルール（UTC/JST変換、Httpファサード使用、秘密情報管理、migration運用など）は除く、プロジェクト固有の設計判断のみ記載。

- **`favorite_teams`は物理削除方針**（`sync()`を使用）。理由は`docs/table-definition.md`13章に明記済み（過去の事実の記録ではなく現在の状態を表すデータであり、`UNIQUE(user_id, team_id)`制約や`sync()`との相性から）。
- **`sync()`を使うチェックボックスUIは、既存データを画面に反映してから使う**：お気に入りチーム選択画面で、既存の登録状態をチェックボックスに反映していなかったため、何も操作せず保存するだけで既存データが消えるバグがあった（PR #24で修正）。今後`sync()`ベースの選択UIを作る際は、初期表示時に現在の状態を`@checked()`等で反映することを徹底する。
- **お気に入りチーム選択画面のみ、JSでの表示切り替えを採用**：大会を切り替えるたびにページ全体をリロードすると選択済みチェック状態が失われるため、全チームを1回のクエリで読み込み、JSで`data-competitions`属性に応じて表示/非表示を切り替える設計にした。他の画面は基本的にフォーム送信＋ページリロード（Ajax不使用）方針を維持。
- **試合カードのマークアップは`resources/views/matches/_card.blade.php`に部分ビュー化**、ステータス表示ロジック（ラベル・色）は`FootballMatch::statusInfo()`としてモデル側に集約済み。今後カード表示を追加する画面があれば、この部分ビューとメソッドを再利用する。
- **`MatchController@show`はroute model bindingを使わず`int $id`で実装**（"問題B"として認識済み、特に不都合が出ていないため現状維持でユーザーと合意済み）。

## 未解決の問題

なし。前回記載していたヘッダー切り替えリンクのホバースタイル未対応は、今回のセッションで修正済み（未コミット、上記「Gitの未コミット状態」参照）。

## 次にやること

- `chore/header-hover-style-fix`ブランチのPRのレビュー・マージ

次に着手する**新機能**の選定は`/next-task`の役割のためここには記載しない。

## 次のセッションへの注意点

- ローカル環境はLaravel Sail（Docker）。Artisanコマンド・テスト・Pint・PHPStanはすべて`./vendor/bin/sail`経由で実行する。
- `Competition` / `Team` / `FootballMatch`にはFactoryが無く、テストでは`Model::create([...])`で直接テストデータを作成する慣習になっている（`User`のみFactory有り）。
- `/review-pr`実行時は、作業ブランチが直前にマージ済みの別タスクの名前のまま残っていないか（＝新しい作業を無関係なブランチ名の上で続けていないか）を確認する習慣がある。該当する場合は最新の`main`から新しいブランチを切り直す。同様に、レビュー中に本題と無関係な問題（他ファイルの既存の崩れ等）を見つけた場合も、別ブランチ・別PRに切り出してから本題を続ける運用にしている（直近ではPint整形をPR #23として切り出した）。
- コミットメッセージ末尾に`Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>`と`Claude-Session:`行を付与する慣習がある。
- 作業用のfeatureブランチは、マージ後にローカルで`git checkout main && git pull`せず放置すると、そのブランチの作業ツリーだけ古いファイル内容のままになる（`main`にマージ済みでも、ブランチ自体がmainの更新を取り込むわけではない）。次のセッション開始時は`main`へ切り替えて`git pull`しておくと状態確認が正確になる。
- 今回`main`ブランチに直接チェックアウトしたまま`/style-view`でアプリコード（`layouts/app.blade.php`）を修正してしまい、ブランチを切らずに変更が乗ってしまった。基本方針は「アプリコードの変更は必ずfeatureブランチ上で行う」ため、次回以降は作業前に`git branch --show-current`で`main`にいないか確認する。
