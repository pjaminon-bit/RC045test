#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$root"

printf '== JavaScript syntax ==\n'
while IFS= read -r -d '' js; do
  node --check "$js"
done < <(find . -type f -name '*.js' \
  -not -path './node_modules/*' \
  -not -path './vendor/*' \
  -not -path './playwright-report/*' \
  -print0)

printf '\n== PHP regressiesuites ==\n'
mapfile -d '' tests < <(find tests -maxdepth 1 -type f -name '*.php' -print0 | sort -z)
if (( ${#tests[@]} == 0 )); then
  echo 'FOUT: geen PHP-regressietests gevonden.' >&2
  exit 1
fi

for test_file in "${tests[@]}"; do
  printf '\n>>> %s\n' "$test_file"
  php "$test_file"
done

printf '\n== Repositorygrenzen ==\n'
test ! -e site-config.local.php

echo "ALLE REGRESSIETESTS GESLAAGD (${#tests[@]} PHP-tests)"
