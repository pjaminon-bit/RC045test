<?php
// Security boundary voor privileged subprocesses uit immutable platformreleases.
// Een trusted release mag als root nooit PHP uit een andere/newere release
// uitvoeren. Alleen twee expliciete kandidaatchecks zijn toegestaan:
// - php -l wordt onder nobody uitgevoerd;
// - health wordt uitgevoerd met de checker uit de trusted callerrelease.

function process521ReleaseRootFromReal(string $real): ?string
{
    if (preg_match('#^(/srv/verenigingsplatform/releases/[0-9a-f]{40})(?:/|$)#D', $real, $m) !== 1) return null;
    return $m[1];
}

function process521ReleaseRoot(string $path): ?string
{
    $real = realpath($path);
    if ($real === false) return null;
    return process521ReleaseRootFromReal($real);
}

function process521TrustedRunnerReleaseRoot(): ?string
{
    return process521ReleaseRoot(__FILE__);
}

function process521PhpBinary(string $binary): bool
{
    return preg_match('#^/usr/bin/php(?:[0-9]{1,2}\.[0-9]{1,2})?$#D', $binary) === 1;
}

function process521RootPhpBoundary(array $cmd): array
{
    if (PHP_OS_FAMILY !== 'Linux' || !function_exists('posix_geteuid') || posix_geteuid() !== 0) return $cmd;
    if ($cmd === [] || !isset($cmd[0]) || !process521PhpBinary((string)$cmd[0])) return $cmd;

    // De boundary is actief wanneer deze helper zelf uit een immutable live
    // release geladen is. First-VPS bootstrap uit een apart root-owned,
    // manifestgebonden source tree behoudt zo zijn bestaande bootstrapflow.
    $trustedRoot = process521TrustedRunnerReleaseRoot();
    if ($trustedRoot === null) return $cmd;

    // PHP lint compileert kandidaatcode maar mag dat niet als root doen.
    if (($cmd[1] ?? null) === '-l' && isset($cmd[2]) && count($cmd) === 3) {
        $candidate = realpath((string)$cmd[2]);
        $candidateRoot = $candidate === false ? null : process521ReleaseRootFromReal($candidate);
        if ($candidateRoot !== null && !hash_equals($trustedRoot, $candidateRoot)) {
            $runuser = '/usr/sbin/runuser';
            if (!is_file($runuser) || !is_executable($runuser)) {
                throw new RuntimeException('Kandidaat-PHP lint vereist beschikbare runuser voor privilege-drop.');
            }
            return [$runuser, '-u', 'nobody', '--', (string)$cmd[0], '-l', $candidate];
        }
        if ($candidateRoot !== null && hash_equals($trustedRoot, $candidateRoot)) $cmd[2] = $candidate;
        return $cmd;
    }

    // Normale PHP-scriptinvocatie: alleen dezelfde trusted release mag root
    // krijgen. Een logisch current-pad naar diezelfde release wordt eerst naar
    // het fysieke immutable pad gecanonicaliseerd. Een healthcheck op een
    // kandidaat wordt teruggebonden aan de checker uit de trusted release;
    // overige cross-release PHP faalt gesloten.
    if (!isset($cmd[1]) || !is_string($cmd[1]) || !str_ends_with(strtolower($cmd[1]), '.php')) return $cmd;
    $child = realpath($cmd[1]);
    $childRoot = $child === false ? null : process521ReleaseRootFromReal($child);
    if ($childRoot === null) return $cmd;
    if (hash_equals($trustedRoot, $childRoot)) {
        $cmd[1] = $child;
        return $cmd;
    }

    if (basename($child) === 'check-vps-health.php') {
        $trustedChecker = realpath($trustedRoot . '/bin/check-vps-health.php');
        if ($trustedChecker === false || !is_file($trustedChecker) || is_link($trustedChecker)
            || !hash_equals($trustedRoot, process521ReleaseRootFromReal($trustedChecker) ?? '')) {
            throw new RuntimeException('Trusted healthchecker ontbreekt of valt buiten de callerrelease.');
        }
        $cmd[1] = $trustedChecker;
        return $cmd;
    }

    throw new RuntimeException('Root-PHP naar een andere of kandidaat-release is geblokkeerd.');
}
