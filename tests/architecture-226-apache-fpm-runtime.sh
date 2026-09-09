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
if ! command -v sudo >/dev/null 2>&1 || ! sudo -n true >/dev/null 2>&1; then
  echo 'SKIP: passwordloze sudo ontbreekt; productiegetrouwe geïsoleerde FPM-user kan niet veilig worden getest.'
  exit 0
fi

tmp="${RUNNER_TEMP:-/tmp}/rc045-226-fpm-$RANDOM-$$"
platform="$tmp/platform"
release="$platform/releases/test-release"
public="$release/public"
tenant="$tmp/tenant"
private="$tenant/private"
sessions="$private/sessions"
upload_tmp="$private/tmp"
socket="$tmp/php-fpm.sock"
port="$((24000 + ($$ % 16000)))"
apache_pid=''
fpm_pid=''
fpm_user=''
fpm_group=''
identity_created=0

cleanup() {
  if [[ -n "$apache_pid" ]]; then
    kill -TERM "$apache_pid" 2>/dev/null || true
    wait "$apache_pid" 2>/dev/null || true
  fi
  if [[ -n "$fpm_pid" ]]; then
    sudo kill -TERM "$fpm_pid" 2>/dev/null || true
    wait "$fpm_pid" 2>/dev/null || true
  fi
  sudo rm -rf "$tmp" 2>/dev/null || true
  if [[ "$identity_created" == 1 && -n "$fpm_user" ]]; then
    sudo userdel "$fpm_user" 2>/dev/null || true
    sudo groupdel "$fpm_group" 2>/dev/null || true
  fi
}
trap cleanup EXIT

mkdir -p "$release" "$tenant" "$private" "$sessions" "$upload_tmp" "$tmp/run"
# Gebruik echte repositorycode zonder git/node-artifacts. De logische current-
# symlink bootst de immutable VPS-releasegrens na.
tar -C "$root" \
  --exclude='.git' --exclude='node_modules' --exclude='vendor' \
  --exclude='playwright-report' --exclude='test-results' \
  -cf - . | tar -C "$release" -xf -
ln -s 'releases/test-release' "$platform/current"
chmod 0755 "$tmp" "$tmp/run"

fpm_user="$(php -r 'require $argv[1] . "/app/deployment/runtime-contract.php"; echo runtime41VerwachteOsUser("test");' "$root")"
fpm_group="$fpm_user"
if getent passwd "$fpm_user" >/dev/null 2>&1 || getent group "$fpm_group" >/dev/null 2>&1; then
  echo "FOUT: tijdelijke productie-identiteit bestaat onverwacht al: $fpm_user" >&2
  exit 1
fi
sudo groupadd --system "$fpm_group"
sudo useradd --system --gid "$fpm_group" --home-dir /nonexistent --shell /usr/sbin/nologin --no-create-home "$fpm_user"
identity_created=1

cat > "$tenant/config.php" <<EOF
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

# Bootst de productiegrenzen na: immutable root:root releasecode, metadata
# root:<tenantgroep> en mutable private data exclusief voor de tenant-user.
sudo chown root:root "$platform" "$platform/releases"
sudo chmod 0755 "$platform" "$platform/releases"
sudo chown -h root:root "$platform/current"
sudo chown -R root:root "$release"
sudo find "$release" -type d -exec chmod 0555 {} +
sudo find "$release" -type f -exec chmod 0444 {} +
sudo chown root:"$fpm_group" "$tenant" "$tenant/config.php"
sudo chmod 0750 "$tenant"
sudo chmod 0640 "$tenant/config.php"
sudo chown -R "$fpm_user":"$fpm_group" "$private"
sudo chmod 0750 "$private"
sudo chmod 0700 "$sessions" "$upload_tmp"

web_user="$(id -un)"
web_group="$(id -gn)"
pool_config="$tmp/pool.conf"

# Genereer de pool met exact dezelfde productiehelper als fase 4.1. De FPM-
# master draait als root en laat de worker echt zakken naar de deterministische
# tenant-identiteit; dit is de permissiegrens die de VPS ook gebruikt.
php -r '
require $argv[1] . "/app/deployment/runtime-contract.php";
$plan = [
    "os" => ["user" => $argv[2], "group" => $argv[3]],
    "php_fpm" => [
        "pool" => "vst-test-9f86d081884c",
        "socket" => $argv[4],
        "listen_owner" => $argv[5],
        "listen_group" => $argv[6],
        "listen_mode" => "0660",
        "clear_env" => true,
        "one_pool_per_tenant" => true,
        "pm" => "ondemand",
        "pm_max_children" => 2,
        "pm_process_idle_timeout" => "5s",
        "pm_max_requests" => 50,
        "session_save_path" => $argv[7],
        "upload_tmp_dir" => $argv[8],
        "runtime_env" => [
            "VERENIGING_REQUIRE_TENANT_CONFIG" => "1",
            "VERENIGING_CONFIG_FILE" => $argv[9],
            "VERENIGING_PRIVATE_ROOT" => $argv[10],
        ],
    ],
];
echo runtime41FpmConfig($plan);
' "$root" "$fpm_user" "$fpm_group" "$socket" "$web_user" "$web_group" "$sessions" "$upload_tmp" "$tenant/config.php" "$private" > "$pool_config"

grep -F "user = $fpm_user" "$pool_config" >/dev/null
grep -F 'clear_env = yes' "$pool_config" >/dev/null
grep -F 'listen.mode = 0660' "$pool_config" >/dev/null
grep -F 'env[VERENIGING_REQUIRE_TENANT_CONFIG] = "1"' "$pool_config" >/dev/null
grep -F "env[VERENIGING_CONFIG_FILE] = \"$tenant/config.php\"" "$pool_config" >/dev/null
grep -F "php_admin_value[session.save_path] = \"$sessions\"" "$pool_config" >/dev/null

touch "$tmp/php-error.log"
sudo chown "$fpm_user":"$fpm_group" "$tmp/php-error.log"
sudo chmod 0644 "$tmp/php-error.log"
cat > "$tmp/php-fpm.conf" <<EOF
[global]
pid = $tmp/php-fpm.pid
error_log = $tmp/php-fpm.log
daemonize = no

EOF
cat "$pool_config" >> "$tmp/php-fpm.conf"
cat >> "$tmp/php-fpm.conf" <<EOF
php_admin_flag[log_errors] = on
php_admin_value[error_log] = $tmp/php-error.log
EOF

sudo "$fpm_bin" --nodaemonize --fpm-config "$tmp/php-fpm.conf" >"$tmp/fpm-stdout.log" 2>&1 &
fpm_pid=$!
for _ in $(seq 1 50); do
  [[ -S "$socket" ]] && break
  if ! sudo kill -0 "$fpm_pid" 2>/dev/null; then break; fi
  sleep 0.1
done
if [[ ! -S "$socket" ]]; then
  cat "$tmp/fpm-stdout.log" >&2 || true
  sudo cat "$tmp/php-fpm.log" >&2 || true
  echo 'FOUT: tijdelijke PHP-FPM voor #226 kwam niet beschikbaar.' >&2
  exit 1
fi

# Genereer de public-root routing rechtstreeks uit het fase-4.2 contract.
fragment="$tmp/routing.conf"
php -r '
require $argv[1] . "/app/deployment/webserver-contract.php";
$platform = $argv[2];
$plan = [
  "shared_code" => ["app_root" => $platform . "/current", "document_root" => $platform . "/current/public"],
  "php_fpm" => ["socket" => $argv[3], "backend" => "fcgi://vst-test-9f86d081884c/"],
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
  echo 'FOUT: tijdelijke HTTPS-Apache voor #226 kwam niet beschikbaar.' >&2
  exit 1
fi

probe() {
  local path="$1" expected="$2" body="$tmp/body" status
  status="$(curl "${curl_base[@]}" --path-as-is -sS -o "$body" -w '%{http_code}' --connect-timeout 3 --max-time 20 "https://test.vps.holox.nl:$port$path" || true)"
  printf '%-38s status=%s expected=%s\n' "$path" "$status" "$expected"
  if [[ "$status" != "$expected" ]]; then
    echo '--- response body ---' >&2
    cat "$body" >&2 || true
    echo '--- generated FPM pool ---' >&2
    cat "$pool_config" >&2 || true
    echo '--- generated HTTPS routing fragment ---' >&2
    cat "$fragment" >&2 || true
    echo '--- Apache error log ---' >&2
    cat "$tmp/apache-error.log" >&2 || true
    echo '--- PHP-FPM log ---' >&2
    sudo cat "$tmp/php-fpm.log" >&2 || true
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

echo 'Architecture #226 geïsoleerde productie-identiteit + gegenereerde public-root + echte PHP-FPM runtime: OK'
