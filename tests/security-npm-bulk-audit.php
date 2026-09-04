<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;

function c194(bool $conditie, string $label): void
{
    global $ok, $fout;
    if ($conditie) {
        $ok++;
        echo "OK: {$label}\n";
        return;
    }

    $fout++;
    fwrite(STDERR, "FOUT: {$label}\n");
}

$workflow = (string) file_get_contents($root . '/.github/workflows/security-supply-chain.yml');
$auditor = (string) file_get_contents($root . '/scripts/security-npm-bulk-audit.js');

c194(
    str_contains($workflow, 'npm ci --ignore-scripts --no-audit'),
    'npm ci gebruikt geen impliciete niet-blokkerende audit naast de expliciete securitygate'
);
c194(
    str_contains($workflow, 'node scripts/security-npm-bulk-audit.js --self-test')
        && str_contains($workflow, 'node scripts/security-npm-bulk-audit.js package-lock.json'),
    'securityworkflow voert parserzelftest en echte lockfile-audit uit'
);
c194(
    !str_contains($workflow, 'npm audit --audit-level=high')
        && !str_contains($auditor, '/-/npm/v1/security/audits/quick'),
    'workflow gebruikt niet langer het kapotte Quick Audit fallbackpad'
);
c194(
    str_contains($auditor, "https://registry.npmjs.org/-/npm/v1/security/advisories/bulk"),
    'dependency-auditor gebruikt het gedocumenteerde Bulk Advisory endpoint'
);
c194(
    str_contains($auditor, 'inhoud[0] === 0x1f')
        && str_contains($auditor, 'inhoud[1] === 0x8b')
        && str_contains($auditor, 'zlib.gunzipSync'),
    'dependency-auditor herkent en verwerkt gzip-body zonder Content-Encoding'
);
c194(
    str_contains($auditor, "new Set(['high', 'critical'])")
        && str_contains($auditor, 'process.exitCode = 1'),
    'HIGH en CRITICAL advisories blijven blokkerend'
);
c194(
    str_contains($auditor, 'AUDIT-SERVICEFOUT:')
        && str_contains($auditor, 'process.exitCode = 2')
        && str_contains($auditor, 'De securitygate blijft gesloten'),
    'service- en parsefouten blijven fail-closed en herkenbaar'
);
c194(
    str_contains($auditor, "const CURL_BIN = '/usr/bin/curl'")
        && str_contains($auditor, "'--http2'")
        && str_contains($auditor, "'--fail'")
        && !str_contains($auditor, "'--fail-with-body'")
        && str_contains($auditor, "'--retry', '2'")
        && str_contains($auditor, "'--retry-all-errors'")
        && str_contains($auditor, "'--connect-timeout', '10'")
        && str_contains($auditor, "'--max-time', '30'")
        && str_contains($auditor, 'shell: false'),
    'Bulk-audit gebruikt shellvrije HTTP/2 curl met parse-veilige retries en timeouts'
);

$always = '${{ always() }}';
$sourceBlok = "- name: Controleer eigen security source policy\n"
    . "        if: {$always}\n"
    . "        run: php tests/security-source-regression.php";
$preVpsBlok = "- name: Controleer pre-VPS securitycontract\n"
    . "        if: {$always}\n"
    . "        run: php tests/security-pre-vps-hardening.php";

c194(
    str_contains($workflow, $sourceBlok),
    'eigen security source policy draait ook wanneer dependency-audit faalt'
);
c194(
    str_contains($workflow, $preVpsBlok),
    'pre-VPS securitycontract draait ook wanneer dependency-audit faalt'
);

echo "Security #194 npm bulk audit: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
