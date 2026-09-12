<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;

function c276(bool $conditie, string $label): void
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

require_once $root . '/app/operational-log.php';

$logPad = tempnam(sys_get_temp_dir(), 'rc045-276-log-');
if (!is_string($logPad) || $logPad === '') {
    fwrite(STDERR, "FOUT: tijdelijke logfixture kon niet worden aangemaakt\n");
    exit(1);
}

$oudeErrorLog = (string) ini_get('error_log');
$oudeLogErrors = (string) ini_get('log_errors');
$canary = 'SECURITY276_CANARY password=supersecret dsn=pgsql:host=internal-db';

try {
    ini_set('log_errors', '1');
    ini_set('error_log', $logPad);

    vpOps46ReportException(
        null,
        'security_276_canary',
        new RuntimeException($canary),
        [
            'component' => 'security_test',
            'code' => 'canary_failure',
            'password' => $canary,
        ]
    );

    clearstatcache(true, $logPad);
    $log = (string) file_get_contents($logPad);

    c276(!str_contains($log, 'SECURITY276_CANARY'), 'vrije exceptiontekst komt niet in fallback-log');
    c276(!str_contains($log, 'supersecret'), 'niet-allowlisted gevoelige context komt niet in fallback-log');
    c276(!str_contains($log, 'internal-db'), 'interne infrastructuurcontext komt niet in fallback-log');
    c276(str_contains($log, 'security_276_canary'), 'stabiele eventcode blijft observeerbaar');
    c276(str_contains($log, 'RuntimeException'), 'exceptionklasse blijft observeerbaar');
    c276(str_contains($log, 'security_test'), 'allowlisted component blijft observeerbaar');
    c276(str_contains($log, 'canary_failure'), 'allowlisted stabiele foutcode blijft observeerbaar');
} finally {
    ini_set('error_log', $oudeErrorLog);
    ini_set('log_errors', $oudeLogErrors);
    @unlink($logPad);
}

$loggerBron = (string) file_get_contents($root . '/app/operational-log.php');
$publicBron = (string) file_get_contents($root . '/public-content.php');
$privateStoreBron = (string) file_get_contents($root . '/app/storage/private-store.php');

c276(
    str_contains($loggerBron, 'function vpOps46ReportException(')
        && !str_contains($loggerBron, '$exception->getMessage()'),
    'centrale exceptionlogger publiceert geen vrije Throwable-message'
);
c276(
    str_contains($publicBron, "vpOps46ReportException(null, 'public_content_store_failure'")
        && !str_contains($publicBron, '->getMessage()'),
    'publieke contentroute gebruikt de gesaneerde exceptionboundary'
);
c276(
    str_contains($privateStoreBron, "privateStoreRapporteerException('private_store_pdo_unavailable'")
        && str_contains($privateStoreBron, "privateStoreRapporteerException('private_store_read_failure'")
        && str_contains($privateStoreBron, "privateStoreRapporteerException('private_store_write_failure'")
        && str_contains($privateStoreBron, "privateStoreRapporteerException('private_store_prebackup_failure'")
        && str_contains($privateStoreBron, "privateStoreRapporteerException('private_store_json_rollback_failure'")
        && str_contains($privateStoreBron, "privateStoreRapporteerException('private_store_json_cleanup_failure'")
        && str_contains($privateStoreBron, "privateStoreRapporteerException('private_store_json_rollback_incomplete'")
        && !str_contains($privateStoreBron, '->getMessage()'),
    'private-store exceptioncatches gebruiken alleen stabiele redacted events'
);
c276(
    str_contains($publicBron, "http_response_code(500);")
        && !str_contains($publicBron, "echo $canary"),
    'publieke foutresponse blijft generiek'
);

echo "Security #276 observability redaction: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);