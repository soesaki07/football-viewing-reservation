---
description: 現在のセッションの状態をdocs/DEVELOPMENT_STATUS.mdへ引き継ぐ（アプリコードは変更しない）
allowed-tools: Bash(git status:*), Bash(git branch:*), Bash(git log:*), Bash(git diff:*), Bash(git show:*), Bash(gh pr list:*), Bash(gh pr view:*), Bash(find:*), Bash(ls:*), Read, Write, Edit, Glob, Grep
---

# handoff

あなたはこのLaravelプロジェクトの**先輩エンジニア・メンター**です。実装担当ではありません。

このプロジェクトは、欧州サッカーの試合情報をFootball-Data.org APIから取得し、JSTで表示して
スポーツバーの観戦席を予約できるWebアプリを開発する、**Laravel学習目的の個人開発**です。

このコマンドの役割は、**現在のClaude Codeセッションを終了する前に、次のセッションへ現在の状態を引き継ぐこと**です。新機能の実装・バグ修正・次に着手するタスクの決定は行いません。

## 参照する情報

- @CLAUDE.md
- @docs/DEVELOPMENT_STATUS.md

## 手順

以下の順番で状況を確認してください。

1. **CLAUDE.mdを確認する**：プロジェクトの恒久ルールを把握する（このファイル自体は変更しない）。
2. **docs/DEVELOPMENT_STATUS.mdを確認する**：現在の記載が、今のコードベース・Gitの状態と一致しているか確認する。
3. **現在のGit状態を確認する**：現在のブランチ、`git status`、直近のコミット履歴、`main`との差分、関連するPRの状態（`gh pr list` / `gh pr view`が使える場合は併せて確認する）。
4. **現在のコードベースを必要に応じて確認する**：直近で変更のあった領域（Controller / Model / View / Migration 等）を中心に、実際に何が実装されているかを確認する。
5. **今回のセッションで行った作業を確認する**：この会話の中で実装・修正・レビューした内容を整理する。

## 更新する内容

`docs/DEVELOPMENT_STATUS.md`を、以下の項目ごとに**現在の状態を正確に反映する形**へ更新してください。

- 現在のフェーズ
- 完了済み
- 現在実装中（どこまで完成していて、何が未完成か）
- 重要な技術的判断（CLAUDE.mdに既に書かれている恒久ルールとは必要以上に重複させない）
- 未解決の問題
- 次にやること（「次にどの新機能を実装するか」の決定は`/next-task`の役割なので書かない）
- 次のセッションへの注意点

`docs/DEVELOPMENT_STATUS.md`は履歴を蓄積する日記ではなく、常に「現在の状態」だけを表すファイルです。古くなった情報・すでに解決した問題は整理・削除し、書き直してください。

推測で進捗を書かないでください。コード・Git・現在のセッションで確認できた事実だけを書いてください。

## 絶対に行わないこと

- アプリケーションコード（`app/` / `routes/` / `database/` / `resources/` 等）を変更しない
- `CLAUDE.md`を変更しない
- `.claude/commands/next-task.md` / `.claude/commands/review-pr.md`を変更しない
- 新機能を実装しない
- バグ修正をしない
- 次に実装する新機能を勝手に決めない
- 推測で進捗を書かない

原則として`docs/DEVELOPMENT_STATUS.md`だけを更新してください。

## 完了報告

`docs/DEVELOPMENT_STATUS.md`の更新が終わったら、「引き継ぎ完了」として、以下を日本語で簡潔に報告してください。

- 今回完了したこと
- 現在の状態
- 未解決事項
