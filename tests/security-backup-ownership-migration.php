<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;

function checkBOM(bool $cond, string $label): void
{
    global $ok, $fout;
    if ($cond) {
        $ok++;
        echo "OK: {$label}\n";
        return;
    }
    $fout++;
    fwrite(STDERR, "FOUT: {$label}\n");
}

$installerPad = $root . '/ops/vps-test-deploy/install-backup-attestation';
$installer = file_get_contents($installerPad);
checkBOM(is_string($installer), 'backup-attestation installer is leesbaar');

if (!is_string($installer)) {
    exit(1);
}

checkBOM(
    str_contains($installer, "TENANT_BASE = Path('/srv/verenigingen')")
    && str_contains($installer, "deployment_file = tenant_dir / 'deployment.json'")
    && str_contains($installer, "os_user = fpm.get('recommended_os_user')")
    && str_contains($installer, "backup_root = backups_root / 'tenant'"),
    'ownershipmigratie deriveert tenant en exacte backupnamespace uit deploymentcontract'
);

checkBOM(
    str_contains($installer, 'st.st_uid == 0')
    && !str_contains($installer, 'st.st_gid == 0')
    && str_contains($installer, '(st.st_mode & 0o022) == 0'),
    'deploymentvertrouwen vereist root-owner en geen group/world-write zonder onjuiste root-group-eis'
);

checkBOM(
    str_contains($installer, "validate_runtime_parent(private_root, uid, gid, 'private_root')")
    && str_contains($installer, "validate_runtime_parent(backups_root, uid, gid, 'backups-root')")
    && str_contains($installer, "if owner != (0, 0):"),
    'migratie vereist gezonde tenant-owned ouders en accepteert uitsluitend root-owned legacy root'
);

checkBOM(
    str_contains($installer, 'stat.S_ISLNK(st.st_mode)')
    && str_contains($installer, 'special filesystemobject geweigerd')
    && str_contains($installer, 'st.st_nlink != 1')
    && str_contains($installer, 'group/world-writeable object geweigerd'),
    'migratie weigert symlinks special files hardlinks en writeable drift fail-closed'
);

checkBOM(
    str_contains($installer, "getattr(os, 'O_NOFOLLOW', 0)")
    && str_contains($installer, 'os.fstat(fd)')
    && str_contains($installer, 'os.fchown(fd, uid, gid)')
    && str_contains($installer, 'os.fchmod(fd, DIR_MODE if is_dir else FILE_MODE)'),
    'metadataherstel gebruikt fd-gebaseerde no-follow inodecontrole'
);

$children = strpos($installer, 'for path, dev, ino, is_dir in entries:');
$rootLast = strpos($installer, 'apply_snapshot(backup_root, root_identity[0], root_identity[1], True, uid, gid)');
checkBOM(
    $children !== false && $rootLast !== false && $rootLast > $children,
    'backupnamespace-root wordt pas na descendants tenant-owned gemaakt'
);

checkBOM(
    str_contains($installer, 'backup_namespace_contract repair')
    && str_contains($installer, 'backup_namespace_contract check'),
    'installer migreert legacy drift en --check bewaakt daarna het contract'
);

checkBOM(
    !preg_match('/\bchown\s+-R\b/', $installer)
    && !str_contains($installer, '/srv/verenigingen/test')
    && str_contains($installer, 'ReadOnlyPaths=/srv/verenigingen'),
    'repair gebruikt geen brede recursive chown geen test-tenanthardcode en verzwakt attestor sandbox niet'
);

exec('bash -n ' . escapeshellarg($installerPad) . ' 2>&1', $bashOut, $bashCode);
checkBOM($bashCode === 0, 'backup-attestation installer heeft geldige Bash-syntax');

$marker = '  "${PYTHON}" - "${mode}" <<\'PY\'' . "\n";
$start = strpos($installer, $marker);
$pythonCode = null;
if ($start !== false) {
    $start += strlen($marker);
    $end = strpos($installer, "\nPY\n}", $start);
    if ($end !== false) {
        $pythonCode = substr($installer, $start, $end - $start);
    }
}
checkBOM(is_string($pythonCode) && $pythonCode !== '', 'embedded ownershipmigratie-Python is eenduidig uit installer te halen');

if (is_string($pythonCode) && $pythonCode !== '') {
    $tmp = tempnam(sys_get_temp_dir(), 'rc045-backup-owner-');
    if ($tmp === false) {
        checkBOM(false, 'tijdelijk Pythonbestand beschikbaar');
    } else {
        file_put_contents($tmp, $pythonCode);
        exec('python3 -m py_compile ' . escapeshellarg($tmp) . ' 2>&1', $pyOut, $pyCode);
        checkBOM($pyCode === 0, 'embedded ownershipmigratie-Python compileert');
        @unlink($tmp);
        @unlink($tmp . 'c');
        $cache = dirname($tmp) . '/__pycache__';
        if (is_dir($cache)) {
            foreach (glob($cache . '/' . basename($tmp) . '*.pyc') ?: [] as $pyc) @unlink($pyc);
            @rmdir($cache);
        }
    }
}

echo "Security backup ownership migration: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
