#!/usr/bin/env bash
set -euo pipefail

BASE="${LIVE_DEV_BASE_URL:-https://rc045.nl/dev}"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

fail=0
ok() { printf 'OK: %s\n' "$1"; }
bad() { printf 'FOUT: %s\n' "$1" >&2; fail=$((fail+1)); }
status() { curl --silent --show-error --output /dev/null --write-out '%{http_code}' --connect-timeout 10 --max-time 30 "$1"; }

# Baseline headers op de publieke entrypoint.
curl --silent --show-error --dump-header "$TMP/headers" --output "$TMP/home" --connect-timeout 10 --max-time 30 "$BASE/"
tr -d '\r' < "$TMP/headers" > "$TMP/headers.clean"
headers="$(cat "$TMP/headers.clean")"

if grep -Eqi '^strict-transport-security:[[:space:]]*.+max-age=' "$TMP/headers.clean"; then ok 'HSTS actief'; else bad 'HSTS ontbreekt'; fi
if grep -Eqi '^x-content-type-options:[[:space:]]*nosniff' "$TMP/headers.clean"; then ok 'X-Content-Type-Options nosniff actief'; else bad 'X-Content-Type-Options nosniff ontbreekt'; fi
if grep -Eqi '^referrer-policy:' "$TMP/headers.clean"; then ok 'Referrer-Policy actief'; else bad 'Referrer-Policy ontbreekt'; fi
if grep -Eqi '^content-security-policy:' "$TMP/headers.clean" || grep -Eqi '^x-frame-options:[[:space:]]*(deny|sameorigin)' "$TMP/headers.clean"; then ok 'clickjackingbescherming actief'; else bad 'clickjackingbescherming ontbreekt'; fi
if ! grep -Eqi '^x-powered-by:' "$TMP/headers.clean"; then ok 'geen X-Powered-By disclosure'; else bad 'X-Powered-By lekt runtimeinformatie'; fi

# Onveilige HTTP-methoden mogen geen succesvolle response opleveren.
trace="$(curl --silent --show-error --request TRACE --output /dev/null --write-out '%{http_code}' --connect-timeout 10 --max-time 30 "$BASE/" || true)"
case "$trace" in 403|405|501) ok "TRACE geblokkeerd ($trace)";; *) bad "TRACE onverwacht toegestaan/status $trace";; esac

# Bestaande harde servergrenzen, plus meerdere traversal-encodings.
declare -A expected=(
  ["$BASE/app/deployment/first-vps-bootstrap-contract.php"]="403"
  ["$BASE/bin/apply-first-vps-bootstrap.php"]="403"
  ["$BASE/tests/phase52-first-vps-bootstrap.php"]="403"
  ["$BASE/dev-build.json"]="403"
  ["$BASE/public-content.php?key=../auth/users"]="404"
  ["$BASE/public-content.php?key=%2e%2e%2fauth%2fusers"]="404"
  ["$BASE/public-content.php?key=%252e%252e%252fauth%252fusers"]="404"
  ["$BASE/public-asset.php?scope=sponsors&path=../../auth/users.json"]="404"
  ["$BASE/public-asset.php?scope=sponsors&path=https://example.com/x.jpg"]="404"
)
for url in "${!expected[@]}"; do
  got="$(status "$url" || true)"
  if [[ "$got" == "${expected[$url]}" ]]; then ok "$url -> $got"; else bad "$url -> $got, verwacht ${expected[$url]}"; fi
done

# Eenvoudige reflected-XSS-canary: payload mag nooit letterlijk als actieve markup terugkomen.
payload='%3Cscript%3Ewindow.__RC045_XSS__%3D1%3C%2Fscript%3E'
curl --silent --show-error --location --output "$TMP/xss" --connect-timeout 10 --max-time 30 "$BASE/?lang=$payload"
if ! grep -Fqi '<script>window.__RC045_XSS__=1</script>' "$TMP/xss"; then ok 'XSS-canary wordt niet als actieve scriptmarkup gereflecteerd'; else bad 'XSS-canary wordt onveilig gereflecteerd'; fi

# CRLF/header-injection canary via taalparameter mag geen geïnjecteerde header maken.
curl --silent --show-error --dump-header "$TMP/crlf" --output /dev/null --connect-timeout 10 --max-time 30 "$BASE/?lang=nl%0d%0aX-RC045-Injected:%20yes" || true
if ! tr -d '\r' < "$TMP/crlf" | grep -Eqi '^X-RC045-Injected:[[:space:]]*yes'; then ok 'geen CRLF headerinjectie via query'; else bad 'CRLF headerinjectie mogelijk'; fi

# Cookies die op login-entrypoints worden gezet moeten hardened zijn.
for route in beheer/ leden/; do
  curl --silent --show-error --dump-header "$TMP/cookie-$route" --output /dev/null --connect-timeout 10 --max-time 30 "$BASE/$route"
  mapfile -t cookies < <(tr -d '\r' < "$TMP/cookie-$route" | grep -i '^set-cookie:' || true)
  if (( ${#cookies[@]} == 0 )); then
    ok "$route zet vóór login geen cookie"
    continue
  fi
  for cookie in "${cookies[@]}"; do
    grep -qi ';[[:space:]]*Secure' <<<"$cookie" || bad "$route cookie mist Secure"
    grep -qi ';[[:space:]]*HttpOnly' <<<"$cookie" || bad "$route cookie mist HttpOnly"
    grep -Eqi ';[[:space:]]*SameSite=(Strict|Lax)' <<<"$cookie" || bad "$route cookie mist veilige SameSite"
  done
  ok "$route cookies gecontroleerd"
done

if (( fail > 0 )); then
  echo "Live DEV security: $fail fout(en)" >&2
  exit 1
fi
echo 'Live DEV security: ALLES GROEN'
