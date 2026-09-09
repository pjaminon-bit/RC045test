#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
apache='/usr/sbin/apache2'

fpm_bin="$(command -v php-fpm 2>/dev/null || true)"
if [[ -z "$fpm_bin" ]]; then
  fpm_bin="$(find /usr/sbin -maxdepth 1 -type f -name 'php-fpm*' -perm -111 2>/dev/null | sort -V | tail -n 1)"
fi

if [[ ! -x "$apache" ]] || [[ -z "$fpm_bin" || ! -x "$fpm_bin" ]] || ! command -v curl >/dev/null 2>&1 || ! command -v openssl >/dev/null 2>&1; then
  echo 'SKIP: Apache/PHP-FPM/curl/openssl ontbreekt voor #226 echte FPM-regressie.'
  exit 0
fi

tmp="${RUNNER_TEMP:-/tmp}/rc045-226-fpm-$RANDOM-$$"
platform="$tmp/platform"
release="$platform/releases/test-release"
public="$release/public"
private="$tmp/private"
socket="$tmp/php-fpm.sock"
port="$((24000 + ($$ % 16000)))"
apache_pid=''
fpm_pid=''

cleanup() {
  if [[ -n "$apache_pid" ]]; then
    kill -TERM "$apache_pid" 2>/dev/null || true
    wait "$apache_pid" 2>/dev/null || true
  fi
  if [[ -n "$fpm_pid" ]]; then
    kill -TERM "$fpm_pid" 2>/dev/null || true
    wait "$fpm_pid" 2>/dev/null || true
  fi
  rm -rf "$tmp"
}
trap cleanup EXIT

mkdir -p "$release" "$private" "$tmp/run"
# Gebruik echte repositorycode zonder git/node artifacts. Zo draait public/index.php
# tegen dezelfde include-keten als de immutable release, maar volledig tijdelijk.
tar -C "$root" \
  --exclude='.git' --exclude='node_modules' --exclude='playwright-report' --exclude='test-results' \
  -cf - . | tar -C "$release" -xf -
ln -s 'releases/test-release' "$platform/current"
chmod 0755 "$tmp" "$platform" "$platform/releases" "$release" "$public" "$private" "$tmp/run"

cat > "$tmp/tenant-config.php" <<EOF
<?php
return [
    'vereniging' => [
        'sleutel' => 'test',
        'naam' => 'Testvereniging',
        'volledige_naam' => 'Testvereniging',
        'slogan' => 'Test',
        'site_url' => 'https://test.vps.holox.nl',
        'timezone' => 'Europe/Amsterdam',
    ],
    'opslag' => [
        'private_driver' => 'json',
        'private_root' => '$private',
    ],
];
EOF
chmod 0644 "$tmp/tenant-config.php"

fpm_user="$(id -un)"
fpm_group="$(id -gn)"
cat > "$tmp/php-fpm.conf" <<EOF
[global]
pid = $tmp/php-fpm.pid
error_log = $tmp/php-fpm.log
daemonize = no

[test]
user = $fpm_user
group = $fpm_group
listen = $socket
listen.mode = 0666
pm = ondemand
pm.max_children = 2
pm.process_idle_timeout = 5s
clear_env = yes
catch_workers_output = yes
decorate_workers_output = no
env[VERENIGING_REQUIRE_TENANT_CONFIG] = 1
env[VERENIGING_CONFIG_FILE] = $tmp/tenant-config.php
env[VERENIGING_PRIVATE_ROOT] = $private
php_admin_flag[log_errors] = on
php_admin_value[error_log] = $tmp/php-error.log
EOF

"$fpm_bin" --nodaemonize --fpm-config "$tmp/php-fpm.conf" >"$tmp/fpm-stdout.log" 2>&1 &
fpm_pid=$!
for _ in $(seq 1 50); do
  [[ -S "$socket" ]] && break
  if ! kill -0 "$fpm_pid" 2>/dev/null; then break; fi
  sleep 0.1
done
if [[ ! -S "$socket" ]]; then
  cat "$tmp/fpm-stdout.log" >&2 || true
  cat "$tmp/php-fpm.log" >&2 || true
  echo 'FOUT: tijdelijke PHP-FPM voor #226 kwam niet beschikbaar.' >&2
  exit 1
fi

# Test zowel het echte fase-4.2 routingfragment als de fase-4.4 HTTPS-wrapper.
# Alleen de luisterpoort wordt voor de niet-root CI-runtime van 443 naar een
# hoge lokale poort vertaald; alle Host/SNI/rewrite/include/FPM-directives komen
# rechtstreeks uit de productiecontracthelpers.
fragment="$tmp/routing.conf"
php -r '
require $argv[1] . "/app/deployment/webserver-contract.php";
$platform = $argv[2];
$plan = [
  "shared_code" => ["app_root" => $platform . "/current", "document_root" => $platform . "/current/public"],
  "php_fpm" => ["socket" => $argv[3], "backend" => "fcgi://vst-226-test/"],
];
echo web42HttpsRoutingFragment($plan);
' "$root" "$platform" "$socket" > "$fragment"

openssl req -x509 -newkey rsa:2048 -nodes -sha256 -days 1 \
  -subj '/CN=test.vps.holox.nl' \
  -addext 'subjectAltName=DNS:test.vps.holox.nl' \
  -keyout "$tmp/tls.key" -out "$tmp/tls.crt" >/dev/null 2>&1
chmod 0600 "$tmp/tls.key"
chmod 0644 "$tmp/tls.crt"

wrapper_source="$tmp/https-vhost-source.conf"
php -r '
require $argv[1] . "/app/deployment/tls-contract.php";
$plan = [
  "canonical_host" => "test.vps.holox.nl",
  "certificate" => ["fullchain" => $argv[2], "privkey" => $argv[3]],
  "apache" => ["routing_fragment_installed" => $argv[4]],
  "security" => ["hsts_seconds" => 31536000],
];
echo tls44TenantHttps($plan);
' "$root" "$tmp/tls.crt" "$tmp/tls.key" "$fragment" > "$wrapper_source"

wrapper="$tmp/https-vhost.conf"
php -r '
$raw=file_get_contents($argv[1]);
$raw=str_replace("<VirtualHost *:443>", "<VirtualHost 127.0.0.1:" . $argv[2] . ">", $raw, $n);
if($n!==1) exit(1);
echo $raw;
' "$wrapper_source" "$port" > "$wrapper"

grep -F 'SSLStrictSNIVHostCheck On' "$wrapper" >/dev/null
grep -F 'RewriteCond %{SSL:SSL_TLS_SNI}' "$wrapper" >/dev/null
grep -F "Include \"$fragment\"" "$wrapper" >/dev/null

: > "$tmp/mime.types"
cat > "$tmp/apache.conf" <<EOF
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
<IfModule !ssl_module>
LoadModule ssl_module /usr/lib/apache2/modules/mod_ssl.so
</IfModule>
<IfModule mime_module>
TypesConfig "$tmp/mime.types"
</IfModule>
ServerName localhost
ErrorLog "$tmp/apache-error.log"
LogLevel warn
Include "$wrapper"
EOF

"$apache" -t -f "$tmp/apache.conf"
"$apache" -f "$tmp/apache.conf" -DFOREGROUND >"$tmp/apache-stdout.log" 2>&1 &
apache_pid=$!

curl_base=(--insecure --resolve "test.vps.holox.nl:$port:127.0.0.1" -H 'Host: test.vps.holox.nl')
ready=0
for _ in $(seq 1 50); do
  if ! kill -0 "$apache_pid" 2>/dev/null; then break; fi
  status="$(curl "${curl_base[@]}" -sS -o /dev/null -w '%{http_code}' --connect-timeout 1 "https://test.vps.holox.nl:$port/styles.css" || true)"
  if [[ "$status" == '200' ]]; then ready=1; break; fi
  sleep 0.1
done
if [[ "$ready" != 1 ]]; then
  cat "$tmp/apache-stdout.log" >&2 || true
  cat "$tmp/apache-error.log" >&2 || true
  cat "$wrapper" >&2 || true
  echo 'FOUT: tijdelijke HTTPS-Apache voor #226 kwam niet beschikbaar.' >&2
  exit 1
fi

probe() {
  local path="$1" expected="$2" body="$tmp/body" status
  status="$(curl "${curl_base[@]}" --path-as-is -sS -o "$body" -w '%{http_code}' --connect-timeout 3 --max-time 20 "https://test.vps.holox.nl:$port$path" || true)"
  printf '%-32s status=%s expected=%s\n' "$path" "$status" "$expected"
  if [[ "$status" != "$expected" ]]; then
    echo '--- response body ---' >&2
    cat "$body" >&2 || true
    echo '--- generated HTTPS wrapper ---' >&2
    cat "$wrapper" >&2 || true
    echo '--- generated routing fragment ---' >&2
    cat "$fragment" >&2 || true
    echo '--- Apache error log ---' >&2
    cat "$tmp/apache-error.log" >&2 || true
    echo '--- PHP-FPM log ---' >&2
    cat "$tmp/php-fpm.log" >&2 || true
    echo '--- PHP error log ---' >&2
    cat "$tmp/php-error.log" >&2 || true
    echo '--- PHP-FPM stdout ---' >&2
    cat "$tmp/fpm-stdout.log" >&2 || true
    return 1
  fi
}

probe '/' 200
probe '/index.php' 200
probe '/styles.css' 200
probe '/app/core/platform-definities.php' 404
probe '/bin/apply-vps-release.php' 404

echo 'Architecture #226 volledige HTTPS-wrapper + echte PHP-FPM public-root runtime: OK'

# Bewijs daarna dat exact de losstaande shadow-operator dezelfde productie-
# artifacts op een tweede tijdelijke loopback-Apache kan testen. De twee
# testflags zijn uitsluitend voor deze niet-root CI-runtime: productie/root
# weigert ze expliciet.
if [[ "${EUID:-$(id -u)}" -ne 0 ]]; then
  runtime_plan="$tmp/runtime-plan.json"
  web_plan="$tmp/web-plan.json"
  tls_plan="$tmp/tls-plan.json"
  jq -n --arg user "$fpm_user" --arg group "$fpm_group" \
    '{settings:{web_user:$user,web_group:$group}}' > "$runtime_plan"
  jq -n \
    --arg host 'test.vps.holox.nl' \
    --arg runtime "$runtime_plan" \
    --arg routing "$fragment" \
    --arg docroot "$platform/current/public" \
    '{canonical_host:$host,source:{runtime_plan_file:$runtime},bundle:{https_routing_fragment:$routing},shared_code:{document_root:$docroot}}' > "$web_plan"
  web_sha="$(sha256sum "$web_plan" | awk '{print $1}')"
  jq -n \
    --arg host 'test.vps.holox.nl' \
    --arg web "$web_plan" \
    --arg sha "$web_sha" \
    --arg vhost "$wrapper_source" \
    '{canonical_host:$host,source:{web_plan_file:$web,web_plan_sha256:$sha},bundle:{tenant_https:$vhost}}' > "$tls_plan"

  SHADOW_ALLOW_TEST_ROOT=1 SHADOW_CURL_INSECURE=1 \
    bash "$root/ops/vps-test-deploy/check-public-root-shadow" \
      --web-plan="$web_plan" \
      --tls-plan="$tls_plan"
else
  echo 'SKIP: shadow-helper testflags zijn bewust verboden onder root.'
fi

echo 'Architecture #226 shadow-preflight runtime: OK'
