#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
apache='/usr/sbin/apache2'

if [[ ! -x "$apache" ]] || ! command -v curl >/dev/null 2>&1; then
  echo 'SKIP: Apache/curl ontbreekt voor #226 runtime-regressie.'
  exit 0
fi

tmp="${RUNNER_TEMP:-/tmp}/rc045-226-apache-$RANDOM-$$"
platform="$tmp/platform"
release="$platform/releases/test-release"
public="$release/public"
port="$((20000 + ($$ % 20000)))"
pid=''

cleanup() {
  if [[ -n "$pid" ]]; then
    kill -TERM "$pid" 2>/dev/null || true
    wait "$pid" 2>/dev/null || true
  fi
  rm -rf "$tmp"
}
trap cleanup EXIT

mkdir -p "$tmp/run" "$public"
: > "$tmp/mime.types"
chmod 0755 "$tmp" "$tmp/run" "$platform" "$platform/releases" "$release" "$public"
ln -s 'releases/test-release' "$platform/current"
cp "$root/public/.htaccess" "$public/.htaccess"
printf 'body{display:block}\n' > "$public/styles.css"
printf '<?php echo "INDEX";\n' > "$public/index.php"
printf '<?php echo "LEAK";\n' > "$public/future.php"
printf '<?php echo "LEAK-UPPER";\n' > "$public/FUTURE.PHP"
chmod 0644 "$public/.htaccess" "$public/styles.css" "$public/index.php" "$public/future.php" "$public/FUTURE.PHP"

fragment="$tmp/routing.conf"
php -r '
require $argv[1] . "/app/deployment/webserver-contract.php";
$platform = $argv[2];
$plan = [
  "shared_code" => ["app_root" => $platform . "/current", "document_root" => $platform . "/current/public"],
  "php_fpm" => ["socket" => $argv[3], "backend" => "fcgi://vst-226-test/"],
];
echo web42HttpsRoutingFragment($plan);
' "$root" "$platform" "$tmp/missing-fpm.sock" > "$fragment"

conf="$tmp/apache.conf"
errorlog="$tmp/error.log"
cat > "$conf" <<EOF
ServerRoot "/etc/apache2"
Define APACHE_RUN_DIR "$tmp/run"
PidFile "$tmp/apache.pid"
DefaultRuntimeDir "$tmp/run"
Listen 127.0.0.1:$port
IncludeOptional /etc/apache2/mods-enabled/*.load
<IfModule !rewrite_module>
LoadModule rewrite_module /usr/lib/apache2/modules/mod_rewrite.so
</IfModule>
<IfModule !headers_module>
LoadModule headers_module /usr/lib/apache2/modules/mod_headers.so
</IfModule>
<IfModule !proxy_module>
LoadModule proxy_module /usr/lib/apache2/modules/mod_proxy.so
</IfModule>
<IfModule !proxy_fcgi_module>
LoadModule proxy_fcgi_module /usr/lib/apache2/modules/mod_proxy_fcgi.so
</IfModule>
<IfModule mime_module>
TypesConfig "$tmp/mime.types"
</IfModule>
ServerName localhost
ErrorLog "$errorlog"
LogLevel warn
EOF

if [[ "$EUID" -eq 0 ]]; then
  cat >> "$conf" <<'EOF'
User www-data
Group www-data
EOF
fi

cat >> "$conf" <<EOF
<VirtualHost 127.0.0.1:$port>
  ServerName test.vps.holox.nl
  Include "$fragment"
</VirtualHost>
EOF

"$apache" -t -f "$conf"
"$apache" -f "$conf" -DFOREGROUND >"$tmp/stdout.log" 2>&1 &
pid=$!

ready=0
for _ in $(seq 1 40); do
  if ! kill -0 "$pid" 2>/dev/null; then break; fi
  status="$(curl -sS -o /dev/null -w '%{http_code}' --connect-timeout 1 -H 'Host: test.vps.holox.nl' "http://127.0.0.1:$port/styles.css" || true)"
  if [[ "$status" != '000' && -n "$status" ]]; then ready=1; break; fi
  sleep 0.2
done

if [[ "$ready" != 1 ]]; then
  cat "$tmp/stdout.log" >&2 || true
  cat "$errorlog" >&2 || true
  echo 'FOUT: tijdelijke Apache voor #226 kwam niet beschikbaar.' >&2
  exit 1
fi

probe() {
  local path="$1" expected="$2" status
  status="$(curl --path-as-is -sS -o /dev/null -w '%{http_code}' --connect-timeout 3 --max-time 10 -H 'Host: test.vps.holox.nl' "http://127.0.0.1:$port$path" || true)"
  printf '%-28s status=%s expected=%s\n' "$path" "$status" "$expected"
  [[ "$status" == "$expected" ]]
}

probe '/styles.css' 200
probe '/future.php' 403
probe '/FUTURE.PHP' 403
probe '/index.php' 503
probe '/healthz.php' 503

if grep -q 'AH00037: Symbolic link not allowed' "$errorlog"; then
  echo 'FOUT: Apache weigert de immutable current-symlink nog steeds.' >&2
  cat "$errorlog" >&2
  exit 1
fi

echo 'Architecture #226 Apache symlink runtime: OK'
