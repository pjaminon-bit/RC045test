#!/usr/bin/env bash
set -euo pipefail

[[ "${EUID:-$(id -u)}" -eq 0 ]] || { echo 'FOUT: voer deze installer als root uit.' >&2; exit 1; }

for tool in /usr/bin/sudo /usr/bin/python3 /usr/bin/php8.5 /usr/sbin/runuser /usr/sbin/visudo /usr/bin/install /usr/bin/readlink; do
  [[ -x "$tool" ]] || { echo "FOUT: vereist programma ontbreekt: $tool" >&2; exit 1; }
done

current="$(/usr/bin/readlink -f /srv/verenigingsplatform/current)"
[[ "$current" =~ ^/srv/verenigingsplatform/releases/[0-9a-f]{40}$ ]] || {
  echo 'FOUT: current wijst niet naar een geldige immutable release.' >&2
  exit 1
}
[[ -f "$current/bin/vps-authenticated-e2e-ephemeral.php" && ! -L "$current/bin/vps-authenticated-e2e-ephemeral.php" ]] || {
  echo 'FOUT: ephemeral E2E-provisioner ontbreekt in de actieve release.' >&2
  exit 1
}

entry=/usr/local/bin/verenigingsplatform-github-entry
e2e=/usr/local/sbin/verenigingsplatform-github-e2e
sudoers=/etc/sudoers.d/verenigingsplatform-github-e2e
[[ -f "$entry" && ! -L "$entry" ]] || { echo 'FOUT: bestaande GitHub forced-command entry ontbreekt of is een symlink.' >&2; exit 1; }
grep -Fq '/usr/local/sbin/verenigingsplatform-github-deploy' "$entry" || {
  echo 'FOUT: bestaande entry bevat het verwachte deploypad niet; installer stopt fail-closed.' >&2
  exit 1
}
id vst-deploy >/dev/null 2>&1 || { echo 'FOUT: systeemaccount vst-deploy ontbreekt.' >&2; exit 1; }

backup="/root/verenigingsplatform-github-entry.pre-e2e.$(date -u +%Y%m%dT%H%M%SZ)"
cp --preserve=mode,ownership,timestamps "$entry" "$backup"

tmp_entry="$(mktemp)"
tmp_e2e="$(mktemp)"
tmp_sudoers="$(mktemp)"
trap 'rm -f "$tmp_entry" "$tmp_e2e" "$tmp_sudoers"' EXIT

cat >"$tmp_entry" <<'ENTRY'
#!/usr/bin/env bash
set -euo pipefail
command="${SSH_ORIGINAL_COMMAND:-}"
if [[ "$command" =~ ^deploy[[:space:]]+([0-9a-f]{40})$ ]]; then
  exec /usr/bin/sudo -n /usr/local/sbin/verenigingsplatform-github-deploy "${BASH_REMATCH[1]}"
fi
case "$command" in
  'e2e check') exec /usr/bin/sudo -n /usr/local/sbin/verenigingsplatform-github-e2e check ;;
  'e2e apply') exec /usr/bin/sudo -n /usr/local/sbin/verenigingsplatform-github-e2e apply ;;
  'e2e cleanup') exec /usr/bin/sudo -n /usr/local/sbin/verenigingsplatform-github-e2e cleanup ;;
esac
echo 'Alleen deploy <40-hex-commit> of e2e check|apply|cleanup is toegestaan.' >&2
exit 64
ENTRY

cat >"$tmp_e2e" <<'E2E'
#!/usr/bin/env bash
set -euo pipefail
[[ "${EUID:-$(id -u)}" -eq 0 ]] || { echo 'FOUT: E2E-wrapper vereist root.' >&2; exit 77; }
[[ "$#" -eq 1 ]] || { echo 'FOUT: exact één E2E-actie vereist.' >&2; exit 64; }
action="$1"
case "$action" in check|apply|cleanup) ;; *) echo 'FOUT: ongeldige E2E-actie.' >&2; exit 64 ;; esac

current="$(/usr/bin/readlink -f /srv/verenigingsplatform/current)"
[[ "$current" =~ ^/srv/verenigingsplatform/releases/[0-9a-f]{40}$ ]] || { echo 'FOUT: ongeldige current release.' >&2; exit 70; }
runtime_plan=/srv/verenigingen/test/runtime/runtime-plan.json
config=/srv/verenigingen/test/config.php
[[ -f "$runtime_plan" && ! -L "$runtime_plan" && -r "$runtime_plan" ]] || { echo 'FOUT: runtime-plan testtenant ongeldig.' >&2; exit 70; }
[[ -f "$config" && ! -L "$config" && -r "$config" ]] || { echo 'FOUT: tenantconfig testtenant ongeldig.' >&2; exit 70; }

runtime_user="$(/usr/bin/python3 - "$runtime_plan" <<'PY'
import json, re, sys
p=sys.argv[1]
with open(p, encoding='utf-8') as f:
    d=json.load(f)
u=str(d.get('os',{}).get('user',''))
if re.fullmatch(r'vst[0-9a-f]{16}',u) is None:
    raise SystemExit(2)
print(u)
PY
)" || { echo 'FOUT: runtime-user kon niet veilig worden bepaald.' >&2; exit 70; }
id "$runtime_user" >/dev/null 2>&1 || { echo 'FOUT: runtime-user bestaat niet.' >&2; exit 70; }
script="$current/bin/vps-authenticated-e2e-ephemeral.php"
[[ -f "$script" && ! -L "$script" && -r "$script" ]] || { echo 'FOUT: E2E-script ontbreekt in actieve release.' >&2; exit 70; }

args=(
  "$script"
  --config="$config"
  --expected-tenant=test
  --expected-site=https://test.vps.holox.nl
  --admin-user=vps-e2e-admin
  --member-user=vps-e2e-member
)
case "$action" in
  check) exec /usr/sbin/runuser -u "$runtime_user" -- /usr/bin/php8.5 "${args[@]}" --check ;;
  apply) exec /usr/sbin/runuser -u "$runtime_user" -- /usr/bin/php8.5 "${args[@]}" --password-stdin --apply ;;
  cleanup) exec /usr/sbin/runuser -u "$runtime_user" -- /usr/bin/php8.5 "${args[@]}" --cleanup ;;
esac
E2E

cat >"$tmp_sudoers" <<'SUDOERS'
vst-deploy ALL=(root) NOPASSWD: /usr/local/sbin/verenigingsplatform-github-e2e check
vst-deploy ALL=(root) NOPASSWD: /usr/local/sbin/verenigingsplatform-github-e2e apply
vst-deploy ALL=(root) NOPASSWD: /usr/local/sbin/verenigingsplatform-github-e2e cleanup
SUDOERS

bash -n "$tmp_entry"
bash -n "$tmp_e2e"
/usr/sbin/visudo -cf "$tmp_sudoers" >/dev/null
/usr/bin/install -o root -g root -m 0755 "$tmp_entry" "$entry"
/usr/bin/install -o root -g root -m 0755 "$tmp_e2e" "$e2e"
/usr/bin/install -o root -g root -m 0440 "$tmp_sudoers" "$sudoers"
/usr/sbin/visudo -cf /etc/sudoers >/dev/null

set +e
negative="$(/usr/sbin/runuser -u vst-deploy -- /usr/bin/env SSH_ORIGINAL_COMMAND='uname -a' "$entry" 2>&1)"
negative_rc=$?
set -e
[[ "$negative_rc" -eq 64 ]] || { echo 'FOUT: forced-command negatieve test faalde.' >&2; exit 1; }
[[ "$negative" == *'Alleen deploy <40-hex-commit> of e2e check|apply|cleanup is toegestaan.'* ]] || { echo 'FOUT: forced-command negatieve melding wijkt af.' >&2; exit 1; }

check="$(/usr/sbin/runuser -u vst-deploy -- /usr/bin/env SSH_ORIGINAL_COMMAND='e2e check' "$entry")"
printf '%s\n' "$check"
grep -Fqx 'E2E EPHEMERAL CHECK OK  tenant=test storage=pdo fixture=vps-authenticated-e2e-v1' <<<"$check" || {
  echo 'FOUT: E2E-gateway check gaf niet de verwachte uitkomst.' >&2
  exit 1
}

echo "E2E GATEWAY INSTALL OK  backup=$backup"
