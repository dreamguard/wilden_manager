#!/usr/bin/env bash
set -euo pipefail

module_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

test -f "$module_dir/wilden_manager.php"
grep -q "class Wilden_manager extends Module" "$module_dir/wilden_manager.php"
grep -q "\$this->name = 'wilden_manager'" "$module_dir/wilden_manager.php"

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

echo 'Wilden Manager validation passed.'
