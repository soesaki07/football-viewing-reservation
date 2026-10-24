#!/usr/bin/env bash
# フックのテスト。止めるべき操作が止まり（終了コード 2）、通常の操作が通る（0）ことを確認する。
# 実行：bash .claude/hooks/test-hooks.sh
set -u

hooks_dir="$(cd "$(dirname "$0")" && pwd)"
project_dir="$(cd "$hooks_dir/../.." && pwd)"
export CLAUDE_PROJECT_DIR="$project_dir"

pass=0
fail=0

# 引数：期待する終了コード / 説明 / フックに渡す JSON / フックのスクリプト
run_case() {
  local expected="$1" label="$2" json="$3" script="${4:-block-dangerous.sh}"
  printf '%s' "$json" | "$hooks_dir/$script" >/dev/null 2>&1
  local actual=$?
  if [[ "${actual}" == "${expected}" ]]; then
    pass=$((pass + 1))
  else
    fail=$((fail + 1))
    echo "NG: ${label}（期待 ${expected} / 実際 ${actual}）"
  fi
}

bash_json() {
  jq -nc --arg c "$1" '{tool_name: "Bash", tool_input: {command: $c}}'
}

file_json() {
  jq -nc --arg t "$1" --arg p "$2" '{tool_name: $t, tool_input: {file_path: $p}}'
}

# --- Bash：止める ---
while IFS= read -r cmd; do
  run_case 2 "止める: ${cmd}" "$(bash_json "${cmd}")"
done <<'EOF'
git push --force
git push origin feature/x --force
git push -f origin feature/x
git push --force-with-lease origin feature/x
git push origin +feature/x
git push origin main
git push origin HEAD:main
git reset --hard
git reset --hard origin/main
git clean -fd
git checkout -- .
git restore .
git stash clear
./vendor/bin/sail artisan migrate:fresh --seed
php artisan migrate:refresh
php artisan db:wipe
mysql -e "DROP TABLE users"
mysql -e "drop database testing"
mysql -e "TRUNCATE TABLE reservations"
rm -rf /
rm -rf ~
rm -rf ~/
rm -rf $HOME
rm -rf .
rm -rf *
sudo rm -rf /
cd /tmp && rm -rf .
cat .env
grep APP_KEY .env
cp .env.example .env
echo "X=1" >> .env
sed -i '' 's/a/b/' .env.local
EOF

# --- Bash：通す ---
while IFS= read -r cmd; do
  run_case 0 "通す: ${cmd}" "$(bash_json "${cmd}")"
done <<'EOF'
git status
git push -u origin feature/main-fix
git push origin chore/add-hooks
git push --follow-tags origin feature/x
git reset HEAD~1
git checkout -- app/Models/Shop.php
git stash list
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan test
php artisan migrate:status
rm -rf vendor
rm -rf node_modules public/build
rm -f storage/logs/laravel.log
cat .env.example
grep FOOTBALL .env.example
ls -la
cat .envrc
EOF

# --- ファイル系ツール ---
run_case 2 "Read .env" "$(file_json Read "$project_dir/.env")"
run_case 2 "Edit .env" "$(file_json Edit "$project_dir/.env")"
run_case 2 "Write .env.production" "$(file_json Write "$project_dir/.env.production")"
run_case 2 "Grep path .env" "$(jq -nc --arg p "$project_dir/.env" '{tool_name: "Grep", tool_input: {pattern: "KEY", path: $p}}')"
run_case 2 "Grep glob .env*" "$(jq -nc '{tool_name: "Grep", tool_input: {pattern: "KEY", glob: ".env*"}}')"
run_case 0 "Read .env.example" "$(file_json Read "$project_dir/.env.example")"
run_case 0 "Edit app/Models/Shop.php" "$(file_json Edit "$project_dir/app/Models/Shop.php")"
run_case 0 "Grep app/" "$(jq -nc --arg p "$project_dir/app" '{tool_name: "Grep", tool_input: {pattern: "Shop", path: $p}}')"

# --- Pint フック ---
tmp_dir="$(mktemp -d)"
trap 'rm -rf "$tmp_dir"' EXIT
printf '<?php\n\n$a = array( 1,2 );\n' >"$tmp_dir/bad.php"
printf '<?php\n\n$a = [1, 2];\n' >"$tmp_dir/good.php"
printf 'not php\n' >"$tmp_dir/note.txt"
run_case 2 "Pint: 違反あり" "$(file_json Edit "$tmp_dir/bad.php")" pint.sh
run_case 0 "Pint: 違反なし" "$(file_json Edit "$tmp_dir/good.php")" pint.sh
run_case 0 "Pint: PHP 以外" "$(file_json Edit "$tmp_dir/note.txt")" pint.sh

echo "成功 ${pass} 件 / 失敗 ${fail} 件"
[[ "${fail}" -eq 0 ]]
