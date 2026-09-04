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

c194(
    str_contains($workflow, 'npm ci --ignore-scripts --no-audit'),
    'gelockte Node-installatie voert geen impliciete npm-audit uit'
);
c194(
    !str_contains($workflow, 'npm audit --audit-level=high')
        && !str_contains($workflow, 'security-npm-bulk-audit.js')
        && !str_contains($workflow, '/-/npm/v1/security/audits/quick'),
    'securitygate is niet meer afhankelijk van npm audit/Quick Audit'
);
c194(
    str_contains($workflow, "versie='2.5.1'")
        && str_contains($workflow, "verwacht='f9f25499a2c8cc367b3af45df2ea7eeca7fbccceab9c35079968f4b3652194be'")
        && str_contains($workflow, 'google/osv-scanner/releases/download/v${versie}/osv-scanner_linux_amd64')
        && str_contains($workflow, 'sha256sum --check --strict'),
    'OSV-Scanner is exact versie- en checksum-gepind op de officiele release'
);
c194(
    str_contains($workflow, 'chmod 0555 "$bin"')
        && str_contains($workflow, 'OSV_SCANNER_BIN=%s')
        && str_contains($workflow, '>> "$GITHUB_ENV"'),
    'geverifieerde OSV-binary wordt alleen uitvoerbaar gemaakt en expliciet doorgegeven'
);
c194(
    str_contains($workflow, '"$OSV_SCANNER_BIN" scan -L package-lock.json --format=vertical'),
    'dependency-audit scant uitsluitend de gelockte package-lock via OSV-Scanner'
);
c194(
    !str_contains($workflow, 'continue-on-error: true')
        && !str_contains($workflow, '|| true'),
    'gevonden kwetsbaarheden of scannerfouten worden niet genegeerd'
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
    'eigen security source policy draait ook als externe dependency-scan faalt'
);
c194(
    str_contains($workflow, $preVpsBlok),
    'pre-VPS securitycontract draait ook als externe dependency-scan faalt'
);

echo "Security #194 dependency audit: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
