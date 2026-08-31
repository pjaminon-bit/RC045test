<?php
// Meervoudige private-store transactie over meerdere collecties.
// PDO gebruikt de bestaande databasetransactie. JSON/standalone gebruikt een
// filesystem-snapshot zodat een latere writefout alle eerdere writes terugzet.

require_once __DIR__ . '/private-store.php';
require_once dirname(__DIR__) . '/core/atomic-file-transaction.php';

function privateStoreBatchTransactie(array $collecties, array $legacyPaden, callable $callback)
{
    $collecties = array_values(array_unique(array_map(static fn($v) => trim((string)$v), $collecties)));
    if ($collecties === [] || in_array('', $collecties, true)) {
        throw new RuntimeException('Private-store batchtransactie mist geldige collecties.');
    }

    if (privateStoreDriver() === 'pdo') {
        return privateStoreTransactie($callback);
    }

    $paden = [];
    $root = privateStoreJsonRoot();
    if ($root !== null) {
        foreach ($collecties as $collectie) {
            $paden[] = tenantRuntimeCollectiePad($root, tenantRuntimeCollectieSleutel($collectie));
        }
    } else {
        if (count($legacyPaden) !== count($collecties)) {
            throw new RuntimeException('Standalone batchtransactie heeft geen volledige padbinding.');
        }
        foreach ($legacyPaden as $pad) {
            $pad = rtrim((string)$pad, '/\\');
            if ($pad === '' || !str_starts_with($pad, DIRECTORY_SEPARATOR)) {
                throw new RuntimeException('Standalone batchtransactie bevat een ongeldig pad.');
            }
            $paden[] = $pad;
        }
    }

    if (count(array_unique($paden)) !== count($paden)) {
        throw new RuntimeException('Private-store batchtransactie bevat dubbele opslagdoelen.');
    }

    return atomicFileTransactie($paden, $callback);
}
