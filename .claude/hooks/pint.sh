#!/usr/bin/env bash
# PostToolUse フック：編集した PHP ファイルを Pint でチェックする。
# 違反があれば終了コード 2 で内容を Claude に返し、Claude が直す（自動整形はしない）。
set -u

input="$(cat)"

if ! command -v jq >/dev/null 2>&1; then
  echo "フック（pint.sh）の実行に jq が必要です。Pint のチェックをスキップしました。" >&2
  exit 0
fi

file="$(printf '%s' "$input" | jq -r '.tool_input.file_path // empty')"
project_dir="${CLAUDE_PROJECT_DIR:-$(pwd)}"

# PHP ファイル以外と vendor 配下は対象外
[[ "${file}" == *.php ]] || exit 0
[[ "${file}" == "$project_dir"/vendor/* ]] && exit 0
[[ -f "${file}" ]] || exit 0

pint="$project_dir/vendor/bin/pint"
if ! command -v php >/dev/null 2>&1 || [[ ! -x "$pint" ]]; then
  echo "php または vendor/bin/pint が見つからないため、Pint のチェックをスキップしました。" >&2
  exit 0
fi

cd "$project_dir" || exit 0

if ! output="$("$pint" --test -v "${file}" 2>&1)"; then
  {
    echo "Pint のスタイル違反があります（${file}）。以下の指摘に合わせて修正してください。"
    echo "$output"
  } >&2
  exit 2
fi

exit 0
