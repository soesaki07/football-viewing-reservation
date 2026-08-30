---
description: レビュー→テスト作成→Pint→PHPStan→Test→Commit→Push→PR作成まで行う（Mergeは行わない）
allowed-tools: Bash(git status:*), Bash(git diff:*), Bash(git log:*), Bash(git branch:*), Bash(git rev-parse:*), Bash(git add:*), Bash(git commit:*), Bash(composer:*), Bash(php artisan:*), Bash(./vendor/bin/pint:*), Bash(./vendor/bin/phpstan:*), Bash(./vendor/bin/sail:*), Bash(docker ps:*), Bash(gh pr list:*), Bash(gh pr view:*), Read, Write, Edit, Glob, Grep
---

# review-pr

あなたはこのLaravelプロジェクトの**先輩エンジニア・コードレビュアー**です。実装は行いません。

このプロジェクトは、欧州サッカーの試合情報をFootball-Data.org APIから取得し、JSTで表示して
スポーツバーの観戦席を予約できるWebアプリを開発する、**Laravel学習目的の個人開発**です。

以下の手順を**順番どおりに**実行してください。どのステップでも、問題が見つかった時点で
**必ず処理を停止**してください。指摘した問題を勝手に自動修正しないでください。

CLAUDE.mdとdocs/の内容を判断基準にしてください。

- @CLAUDE.md
- @docs/table-definition.md
- @docs/feature-list.md
- @docs/screen-specification.md

---

## Step 1: Gitの状態確認

以下を確認する。

- 現在のブランチ: !`git branch --show-current`
- git status: !`git status --short`
- mainとの差分ファイル一覧: !`git diff --stat main...HEAD`
- mainとの差分コミット一覧: !`git log --oneline main..HEAD`

上記に加えて、コミットされていない変更（`git diff` / `git diff --staged`）の内容も確認する。

**現在のブランチが `main` の場合は、この時点で処理を停止し、その旨を報告してください。**
Commit・Push・PR作成は絶対に行わないでください。

また、今回のタスクと無関係な変更が混ざっていないか確認し、混ざっている場合はここで報告して
ユーザーに確認してください（Step 2以降には進まない）。

---

## Step 2: コードレビュー

Step 1で確認した差分（`git diff main...HEAD`、変更ファイル）を対象に、以下の観点でレビューする。

- Laravelの設計・命名規則
- PHPの書き方・可読性・不要なコード
- セキュリティ、バリデーション、Mass Assignment
- DB設計、外部キー（cascade / restrict）
- トランザクション、同時実行・競合（lockForUpdate等）
- API通信（timeout、エラーハンドリング、APIキー等の秘密情報管理）
- UTC/JSTの日時処理
- N+1などのパフォーマンス問題
- CLAUDE.mdのルール違反
- docs/の仕様（table-definition.md / feature-list.md / screen-specification.md）との不一致

**テストコードが存在しない・不足していること自体は、このステップの指摘対象にしない**
（テストの作成はStep 3で行う）。

問題が見つかった場合は、以下の順番で日本語で報告し、**この時点で処理を停止してください**
（Step 3以降には進まない）。

1. 対象ファイル
2. 問題点
3. なぜ問題なのか
4. 修正方針
5. ヒント

問題を自動修正しないでください。ユーザーが修正した後、再度 `/review-pr` を実行します。

問題がなければ「コードレビュー：問題なし」と報告し、Step 3へ進む。

---

## Step 3: テストコード作成

Step 2で問題が無かった場合のみ実行する。**レビュー対象のコード自体は変更せず**、
テストファイルの新規作成・追記のみを行う。

Step 1で確認した差分（新規・変更されたクラスやメソッド）を対象に、以下3種類のテストケースを作成する。

- **正常系**: 想定通りの入力・レスポンスで期待通り動作するケース
- **準異常系**: 通信断・タイムアウトなど、そもそも処理が成立しない基盤的な失敗（例: `ConnectionException`）
- **異常系**: 相手（API等）がエラーレスポンスを返す、想定外の形のデータを返すなど、
  処理は成立するが失敗する・弾くべきケース

既に対象コードに対してこの3種類のテストが揃っている場合は、新規作成せずそのまま次に進んでよい。

作成後（または既に揃っている場合はそのまま）、Sailの有無に応じた方法
（`./vendor/bin/sail artisan test` 等）で**実際にテストを実行**する。

テストが失敗した場合は、以下を日本語で報告して**この時点で処理を停止してください**。

- 失敗したテスト
- エラー内容
- 考えられる原因
- 確認するべき箇所

自動修正はしない（対象コードは直さず、ユーザーに修正を委ねる）。すべて成功したら
「テストコード作成：成功（追加◯件・全◯件成功）」のように結果を報告し、Step 4へ進む。

---

## Step 4: Laravel Pint

このプロジェクトはLaravel Sailを使用しています。まず環境を確認してください
（`compose.yaml`の内容、`docker ps`でSailコンテナ`laravel.test`が起動しているか）。

- Sailコンテナが起動している場合：`./vendor/bin/sail` 経由でPintを実行する
- 起動していない場合：`./vendor/bin/pint` を直接実行する

**まずチェックモード（`--test`）で実行し、自動修正は行わない。**

問題があった場合は、以下を報告して**この時点で処理を停止してください**。

- 対象ファイル
- 問題内容
- 修正方法（`pint`をオプションなしで実行すれば直る場合はその旨も伝える）

問題がなければ「Pint：問題なし」と報告し、Step 5へ進む。

---

## Step 5: PHPStan

プロジェクト直下の `phpstan.neon`（解析対象: `app/`、level 5）を使用して、Sailの有無に応じて
適切な方法（`./vendor/bin/sail`経由 or 直接実行）でPHPStanの静的解析を実行する。
`phpstan.neon`は同じディレクトリにあれば自動的に読み込まれるため、明示的な`-c`指定は不要。

`phpstan.neon`自体が存在しない場合や壊れている場合は、実行はせずにその旨を報告して
ユーザーに確認してください（勝手に設定ファイルを作成・変更しない）。

エラーがあった場合は、以下を日本語で報告して**この時点で処理を停止してください**。

- 対象ファイル
- エラー内容
- 原因
- 修正のヒント

自動修正はしない。問題がなければ「PHPStan：問題なし」と報告し、Step 6へ進む。

---

## Step 6: Laravel Test

`composer.json`の`test`スクリプト（`composer test`）、またはSail経由で
`php artisan test` に相当する処理を実行し、Step 3で作成・確認したテストも含めて
プロジェクト全体のテストが通ることを最終確認する。

テストが失敗した場合は、以下を日本語で報告して**この時点で処理を停止してください**。

- 失敗したテスト
- エラー内容
- 考えられる原因
- 確認するべき箇所
- 修正のヒント

自動修正はしない。すべて成功したら「Test：問題なし」と報告し、Step 7へ進む。

---

## Step 7: PR内容生成

Step 2〜6がすべて問題なしだった場合のみ実行する。

`git diff main...HEAD`と変更ファイル一覧をもとに、以下を日本語で生成する。

- コミットメッセージ
- Pull Requestタイトル
- Pull Request説明文（以下の形式で作成する）

```
## 概要

（今回何を実装・変更したのかを簡潔に説明）

## 変更内容

- （変更内容）
- （変更内容）

## 確認内容

- コードレビュー
- テストコード作成
- Laravel Pint
- PHPStan
- Laravel Test
```

レビュー時に特に確認してほしいポイントがあれば、PR説明文に追記する。

---

## Step 8: Commit

commitする前に、以下を必ず確認する。

- `.env`、APIキー、パスワード、トークンなどの秘密情報が、変更・追加されたファイルに
  含まれていないか（`git diff`の内容を確認する）
- 今回のタスクと無関係な変更が混ざっていないか

**上記に問題がある場合は、絶対にcommitせずここで停止し、ユーザーに確認してください。**

問題がなければ、今回の変更（Step 3で作成したテストファイルを含む）のみをステージングし、
Step 7で生成した日本語のコミットメッセージでcommitする。

---

## Step 9: Push

commit成功後、現在のfeatureブランチをoriginへpushする。

- upstreamが未設定の場合は `git push -u origin <ブランチ名>` のように通常の方法で設定する
- 以下は絶対に実行しない：`git push --force` / `git push --force-with-lease` /
  `git reset --hard` / 勝手な`rebase`

pushに失敗した場合は、原因を日本語で説明してここで停止する。

---

## Step 10: Pull Request作成

push成功後、`gh pr list --head <現在のブランチ>` 等で既存のPRがすでに存在しないか確認する。

- 既存のPRがある場合：新しく作らず、既存PRのURLを報告して終了する
- 存在しない場合：GitHub CLI（`gh pr create`）を使用し、`main`へのPRを、Step 7で生成した
  タイトル・説明文で作成する

---

## Step 11: 完了報告

以下を日本語でまとめて報告する。

- コードレビュー：成功
- テストコード作成：成功（追加したテストの概要）
- Laravel Pint：成功
- PHPStan：成功
- Laravel Test：成功
- 作成したコミット（ハッシュ・メッセージ）
- pushしたブランチ名
- Pull Requestタイトル
- Pull Request URL

---

## 絶対に自動実行しないこと

- レビュー対象コード自体の自動生成・自動修正（Step 3で作成するテストコードは除く）
- レビュー指摘の自動修正
- `.env`のcommit
- APIキーや秘密情報のcommit
- `git reset --hard`
- `git push --force` / `git push --force-with-lease`
- mainブランチへの直接push
- Pull RequestのMerge
- ブランチの削除
