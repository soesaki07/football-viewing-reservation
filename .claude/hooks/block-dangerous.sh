#!/usr/bin/env bash
# PreToolUse フック：危険な操作と .env へのアクセスを実行前に止める。
# 終了コード 2 で止めると、標準エラー出力の内容が Claude に返される。
set -u

input="$(cat)"

if ! command -v jq >/dev/null 2>&1; then
  echo "フック（block-dangerous.sh）の実行に jq が必要です。jq をインストールしてください。" >&2
  exit 2
fi

tool_name="$(printf '%s' "$input" | jq -r '.tool_name // empty')"

block() {
  echo "フックにより実行を止めました：$1" >&2
  echo "必要な操作であれば、回避せずに開発者に依頼してください（開発者が ! で実行します）。" >&2
  exit 2
}

# .env（.env.example 以外）を指すパスかどうか
is_env_path() {
  local name
  name="$(basename -- "$1")"
  [[ "$name" == ".env.example" ]] && return 1
  printf '%s' "$name" | grep -Eq '^\.env(\..+)?$'
}

# コマンド文字列が正規表現に一致するか（行ごとに判定する）
matches() {
  printf '%s' "$command" | grep -Eq -- "$1"
}

matches_i() {
  printf '%s' "$command" | grep -Eqi -- "$1"
}

case "$tool_name" in
  Read | Edit | MultiEdit | Write)
    path="$(printf '%s' "$input" | jq -r '.tool_input.file_path // empty')"
    if [[ -n "${path}" ]] && is_env_path "${path}"; then
      block ".env の読み書きは禁止しています（${path}）"
    fi
    ;;

  NotebookEdit)
    path="$(printf '%s' "$input" | jq -r '.tool_input.notebook_path // empty')"
    if [[ -n "${path}" ]] && is_env_path "${path}"; then
      block ".env の読み書きは禁止しています（${path}）"
    fi
    ;;

  Grep)
    path="$(printf '%s' "$input" | jq -r '.tool_input.path // empty')"
    glob="$(printf '%s' "$input" | jq -r '.tool_input.glob // empty')"
    if [[ -n "${path}" ]] && is_env_path "${path}"; then
      block ".env の読み取りは禁止しています（${path}）"
    fi
    if [[ -n "${glob}" ]] && printf '%s' "${glob}" | grep -Eq '(^|/)\.env(\*|\.[^e*]|$)'; then
      block ".env を対象にした検索は禁止しています（glob: ${glob}）"
    fi
    ;;

  Bash)
    command="$(printf '%s' "$input" | jq -r '.tool_input.command // empty')"
    sep='(^|[;&|(]|[[:space:]])'
    end='([[:space:]]|$|[;&|)])'

    # --- Git ---
    if matches "git[[:space:]]+push([[:space:]]|$)" && {
      matches "git[[:space:]]+push[^;&|]*[[:space:]](--force|--force-with-lease|--force-if-includes)([=[:space:]]|$)" ||
        matches "git[[:space:]]+push[^;&|]*[[:space:]]-[a-zA-Z]*f[a-zA-Z]*([[:space:]]|$)" ||
        matches "git[[:space:]]+push[^;&|]*[[:space:]]\+[^[:space:]]+"
    }; then
      block "force push は禁止しています"
    fi

    if matches "git[[:space:]]+push[^;&|]*([[:space:]]|:)(main|master)$end"; then
      block "main への直接 push は禁止しています（PR を経由してください）"
    fi

    if matches "git[[:space:]]+push([[:space:]]|$)"; then
      current_branch="$(git -C "${CLAUDE_PROJECT_DIR:-.}" branch --show-current 2>/dev/null || true)"
      if [[ "$current_branch" == "main" || "$current_branch" == "master" ]]; then
        block "main ブランチ上での push は禁止しています（作業用ブランチを切ってください）"
      fi
    fi

    if matches "git[[:space:]]+reset[^;&|]*--hard"; then
      block "git reset --hard は禁止しています"
    fi

    if matches "git[[:space:]]+clean[^;&|]*[[:space:]]-[a-zA-Z]*f"; then
      block "git clean -f は禁止しています（未追跡のファイルが消えます）"
    fi

    if matches "git[[:space:]]+(checkout|restore)[[:space:]]+(--[[:space:]]+)?\.$end"; then
      block "作業ツリーの変更をまとめて破棄する操作は禁止しています"
    fi

    if matches "git[[:space:]]+stash[[:space:]]+(clear|drop)"; then
      block "stash の削除は禁止しています"
    fi

    # --- DB ---
    if matches "artisan[[:space:]]+(migrate:(fresh|reset|refresh)|db:wipe)$end"; then
      block "開発用 DB のデータを消すコマンドは禁止しています"
    fi

    if matches_i "(drop[[:space:]]+(table|database|schema)|truncate[[:space:]]+(table[[:space:]]+)?[a-z_\`])"; then
      block "DROP / TRUNCATE は禁止しています"
    fi

    # --- 広い範囲の再帰削除 ---
    if matches "${sep}rm[[:space:]]+(-[^[:space:]]+[[:space:]]+)*(-[a-zA-Z]*[rR][a-zA-Z]*|--recursive)[[:space:]]+(-[^[:space:]]+[[:space:]]+)*(/|/\*|~|~/|~/\*|\\\$HOME|\\\$HOME/|\\\$\{HOME\}|\.|\./|\.\.|\.\./|\*|\./\*)$end"; then
      block "/ や ~、. など広い範囲を再帰的に削除するコマンドは禁止しています"
    fi

    # --- .env（.env.example 以外）への言及 ---
    # 空白・記号で単語に分け、パスの末尾（ファイル名）が .env / .env.xxx のものを探す
    env_refs="$(printf '%s' "$command" | tr -s " \t\n;|&()<>\"'=\`" '\n' | sed -E 's#.*/##' | grep -E '^\.env(\.[A-Za-z0-9_-]+)?$' || true)"
    if [[ -n "$env_refs" ]] && printf '%s\n' "$env_refs" | grep -Evq '^\.env\.example$'; then
      block "Bash から .env を読み書きすることは禁止しています"
    fi
    ;;
esac

exit 0
