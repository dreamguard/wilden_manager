#!/usr/bin/env bash
set -euo pipefail

module_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

test -f "$module_dir/wildenmanager.php"
grep -q "class Wildenmanager extends Module" "$module_dir/wildenmanager.php"
grep -q "\$this->name = 'wildenmanager'" "$module_dir/wildenmanager.php"

while IFS= read -r -d '' php_file; do
  php -l "$php_file" >/dev/null
done < <(find "$module_dir" -type f -name '*.php' -print0)

node --check "$module_dir/views/js/admin-orders.js" >/dev/null
node --check "$module_dir/views/js/native-order-columns.js" >/dev/null
node --check "$module_dir/views/js/configuration.js" >/dev/null

if grep -RniE 'ets_ordermanager|prestahero|ETS_ODE|ets_odm' \
  --exclude-dir=.git --exclude='README.md' --exclude='validate.sh' --exclude='*.zip' "$module_dir"; then
  echo 'Forbidden legacy identifier found in the clean source.' >&2
  exit 1
fi

for xlf in "$module_dir"/translations/*/*.xlf; do
  php -r 'libxml_use_internal_errors(true); exit(simplexml_load_file($argv[1]) === false ? 1 : 0);' "$xlf"
done

python_bin=""
for candidate in python3 python; do
  if "$candidate" --version >/dev/null 2>&1; then python_bin="$candidate"; break; fi
done
if [ -n "$python_bin" ]; then
  "$python_bin" "$module_dir/tools/translations.py" >/dev/null
else
  echo 'Python not found: Spanish translation check skipped.' >&2
fi

php "$module_dir/tests/run.php" >/dev/null

echo 'Wilden Manager validation passed.'
