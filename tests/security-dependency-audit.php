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
$validate = (string) file_get_contents($root . '/.github/workflows/deploy-dev.yml');

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
    'Node dependency-audit scant de gelockte package-lock via OSV-Scanner'
);
c194(
    !str_contains($workflow, 'continue-on-error: true')
        && !str_contains($workflow, '|| true'),
    'gevonden kwetsbaarheden of scannerfouten worden niet genegeerd'
);

c194(
    str_contains($validate, "versie='2.2.13'")
        && str_contains($validate, "verwacht='a3293d850a9966fbef43e39d04f2b081b8eec35839126c43a54684f21b10ad69'")
        && str_contains($validate, 'phpstan/phpstan/releases/download/${versie}/phpstan.phar'),
    'PHPStan is exact aan de officiele 2.2.13 release en gepubliceerde digest gebonden'
);
c194(
    str_contains($validate, "curl --fail --silent --show-error --location --proto '=https' --tlsv1.2")
        && str_contains($validate, 'sha256sum --check --strict')
        && str_contains($validate, 'chmod 0555 "$phpstan"'),
    'PHPStan-download gebruikt HTTPS en wordt fail-closed geverifieerd voor uitvoering'
);
c194(
    str_contains($validate, 'PHPSTAN_PHAR=%s')
        && str_contains($validate, 'php "$PHPSTAN_PHAR" analyse --configuration=phpstan.neon --no-progress --memory-limit=1G'),
    'alleen het geverifieerde PHPStan-PHAR-pad wordt voor analyse uitgevoerd'
);
c194(
    !str_contains($validate, 'composer install')
        && !str_contains($validate, 'composer update')
        && !str_contains($validate, 'composer analyse')
        && !is_file($root . '/composer.json')
        && !is_file($root . '/composer.lock'),
    'required Validate bevat geen runtime Composer-resolutie of overbodige Composer-manifesten meer'
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

echo "Security #194/#274 dependency audit: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);