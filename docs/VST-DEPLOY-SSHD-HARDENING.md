# `vst-deploy` server-side SSH hardening (#136)

Deze policy voegt een tweede, server-side beveiligingslaag toe rond de bestaande restricted GitHub deploykey. De `authorized_keys`-regel met `restrict,command="/usr/local/bin/verenigingsplatform-github-entry"` blijft bewust bestaan; de sshd-laag vervangt die keyrestricties niet.

## Canoniek contract

Repositorybron:

`ops/vps-test-deploy/00-verenigingsplatform-vst-deploy.conf`

Live pad:

`/etc/ssh/sshd_config.d/00-verenigingsplatform-vst-deploy.conf`

Het bestand moet een regulier non-symlinkbestand zijn met `root:root`, mode `0644` en exact de SHA-256 uit `app/deployment/privileged-ops-contract.php`. Daarmee blijft de #135 privileged-artifact-integriteitsbewaking fail-closed intact.

De policy beperkt `vst-deploy` tot public-key authenticatie, weigert password/keyboard-interactive login, PTY, TCP/Unix-socket/X11/agent forwarding, tunnels en user rc, en zet server-side:

`ForceCommand /usr/local/bin/verenigingsplatform-github-entry`

OpenSSH behandelt een server-side `ForceCommand` als de administratieve forced command. Die supersedeert de `command=` key-optie in plaats van beide commando's te nesten. De oorspronkelijk door de client gevraagde opdracht blijft beschikbaar als `SSH_ORIGINAL_COMMAND`; de bestaande gatewayrouter kan daarom ongewijzigd exact `deploy <40-lowercase-hex-sha>` en `e2e check|apply|cleanup` blijven valideren.

`Match All` beëindigt de Match-context expliciet. De bestandsnaam begint met `00-` omdat OpenSSH per keyword de eerste toepasselijke waarde gebruikt; deze least-privilege policy moet vóór eventuele latere drop-ins worden gelezen.

## Gecontroleerde live-installatie

Voer dit uitsluitend uit vanuit een bestaande root/VPS-console of een andere reeds geverifieerde root-sessie. Gebruik een schone checkout van de exact gemergde `main`-commit als `$REPO`. Houd de bestaande rootconsole open totdat de post-checks én een aparte restricted SSH-test groen zijn.

```bash
set -euo pipefail

REPO=/pad/naar/schone/RC045test-checkout
SRC="$REPO/ops/vps-test-deploy/00-verenigingsplatform-vst-deploy.conf"
DST=/etc/ssh/sshd_config.d/00-verenigingsplatform-vst-deploy.conf
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
BACKUP="/root/00-verenigingsplatform-vst-deploy.conf.pre-136.$STAMP"
HAD_OLD=0

# PRE-FLIGHT
[[ "$(id -u)" -eq 0 ]]
[[ -f "$SRC" && ! -L "$SRC" ]]
[[ -d /etc/ssh/sshd_config.d && ! -L /etc/ssh/sshd_config.d ]]
/usr/sbin/sshd -t
sha256sum "$SRC"
git -C "$REPO" status --porcelain=v1 --untracked-files=all | grep -qx '' || {
  echo 'FOUT: checkout is niet schoon.' >&2
  exit 1
}

if [[ -e "$DST" || -L "$DST" ]]; then
  [[ -f "$DST" && ! -L "$DST" ]]
  cp --preserve=mode,ownership,timestamps -- "$DST" "$BACKUP"
  HAD_OLD=1
fi

rollback_136() {
  echo 'ROLLBACK #136' >&2
  if [[ "$HAD_OLD" -eq 1 ]]; then
    install -o root -g root -m 0644 -- "$BACKUP" "$DST"
  else
    rm -f -- "$DST"
  fi
  /usr/sbin/sshd -t
  systemctl reload ssh
}
trap 'rc=$?; if [[ $rc -ne 0 ]]; then rollback_136 || true; fi; exit $rc' EXIT

# WIJZIGING, NOG ZONDER RELOAD
install -o root -g root -m 0644 -- "$SRC" "$DST"
[[ -f "$DST" && ! -L "$DST" ]]
[[ "$(stat -c '%U:%G:%a' "$DST")" == 'root:root:644' ]]
cmp -s -- "$SRC" "$DST"

# VERPLICHTE SYNTAX- EN EFFECTIEVE-CONFIGCONTROLE VOOR RELOAD
/usr/sbin/sshd -t
EFFECTIVE="$(/usr/sbin/sshd -T -C user=vst-deploy,host=github-actions,addr=100.64.0.1,laddr=100.104.242.66,lport=22)"
printf '%s\n' "$EFFECTIVE" | grep -E '^(authenticationmethods|passwordauthentication|kbdinteractiveauthentication|permittty|allowtcpforwarding|allowstreamlocalforwarding|x11forwarding|allowagentforwarding|permittunnel|permituserrc|forcecommand) '
for expected in \
  'authenticationmethods publickey' \
  'passwordauthentication no' \
  'kbdinteractiveauthentication no' \
  'permittty no' \
  'allowtcpforwarding no' \
  'allowstreamlocalforwarding no' \
  'x11forwarding no' \
  'allowagentforwarding no' \
  'permittunnel no' \
  'permituserrc no' \
  'forcecommand /usr/local/bin/verenigingsplatform-github-entry'
do
  grep -Fqx "$expected" <<<"$EFFECTIVE"
done

# RELOAD; bestaande sessies blijven staan.
systemctl reload ssh
systemctl is-active --quiet ssh
/usr/sbin/sshd -t

# POST-CHECK
EFFECTIVE_POST="$(/usr/sbin/sshd -T -C user=vst-deploy,host=github-actions,addr=100.64.0.1,laddr=100.104.242.66,lport=22)"
grep -Fqx 'forcecommand /usr/local/bin/verenigingsplatform-github-entry' <<<"$EFFECTIVE_POST"
sha256sum "$DST"
stat -c 'path=%n owner=%U:%G mode=%a size=%s' "$DST"

trap - EXIT
echo "SSHD HARDENING #136 OK backup=$BACKUP had_old=$HAD_OLD"
```

Als een syntax-, effective-config- of reloadcheck faalt, voert de trap automatisch `ROLLBACK #136` uit. Als een latere netwerkcheck vanuit een tweede sessie onverwacht faalt, voer vanuit de open VPS-console handmatig dezelfde rollback uit: herstel `$BACKUP` wanneer `HAD_OLD=1`, anders verwijder `$DST`; draai daarna `/usr/sbin/sshd -t` en `systemctl reload ssh`.

## Verplichte acceptatie na reload

Gebruik de bestaande GitHub deploykey via het normale Tailscale/known-hosts pad. Bewijs minimaal:

- een shell/ongeautoriseerd commando zoals `uname -a` wordt door de gateway geweigerd;
- een PTY-aanvraag wordt geweigerd;
- normale `deploy <40-lowercase-hex-sha>` blijft werken;
- `e2e check`, `e2e apply` en `e2e cleanup` blijven werken;
- de normale VPS smoke-tests blijven groen;
- de control-plane privileged-ops snapshot toont ook `github-sshd-policy` als `ok`;
- #157 blijft gelden: noch de sshd-policy noch de gateway voert PHP uit `/srv/verenigingsplatform/current` of `/srv/verenigingsplatform/releases` als root uit.

De sshd-wijziging geeft `vst-deploy` geen nieuwe sudo-rechten. De aparte deploy-sudoersfinding #137 blijft daarom een zelfstandige remediation.
