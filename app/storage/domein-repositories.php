<?php
// ============================================================
// Private domeinrepositories
// ============================================================
// Controllers gebruiken deze functies; de huidige PHP+JSON-bestanden zijn
// alleen de fallback-backend. Ook die fallback schrijft hier atomisch via
// temp+rename. Bij private_driver=pdo worden dezelfde domeindocumenten per
// tenant in de database opgeslagen.
// ============================================================
require_once __DIR__ . '/private-store.php';
require_once dirname(__DIR__,2) . '/leden-opslag.php';
require_once dirname(__DIR__,2) . '/vergaderingen-opslag.php';
require_once dirname(__DIR__,2) . '/taken-opslag.php';
require_once dirname(__DIR__,2) . '/operationele-taken-opslag.php';
require_once dirname(__DIR__,2) . '/evenementen-opslag.php';
require_once dirname(__DIR__,2) . '/groepen-opslag.php';
require_once dirname(__DIR__,2) . '/ledenlabels-opslag.php';

function repoPhpJsonSchrijf(string $pad, string $voorloop, array $data, ?callable $backupMaker = null, bool $backup = true): bool
{
    if ($backup && $backupMaker !== null) $backupMaker();
    $data['updated'] = date('c');
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) return false;

    $map = dirname($pad);
    if (!is_dir($map) && !@mkdir($map, 0755, true)) return false;
    try { $suffix = bin2hex(random_bytes(5)); }
    catch (Throwable $e) { $suffix = str_replace('.', '', (string) microtime(true)); }
    $tmp = $pad . '.tmp.' . $suffix;
    if (@file_put_contents($tmp, $voorloop . $json, LOCK_EX) === false) return false;
    if (!@rename($tmp, $pad)) { @unlink($tmp); return false; }
    return true;
}

function repoLedenLees(): array{return privateStoreLees('leden', 'ledenLees');}
function repoLedenSchrijf(array $data, bool $backup=true): bool{return privateStoreSchrijf('leden', $data, static fn($d) => repoPhpJsonSchrijf(ledenBestandPad(), LEDEN_VOORLOOP, $d, 'ledenMaakBackup', $backup));}
function repoVergaderingenLees(): array{return privateStoreLees('vergaderingen', 'vergaderingenLees');}
function repoVergaderingenSchrijf(array $data, bool $backup=true): bool{return privateStoreSchrijf('vergaderingen', $data, static fn($d) => repoPhpJsonSchrijf(vergaderingenBestandPad(), VERGADERINGEN_VOORLOOP, $d, 'vergaderingenMaakBackup', $backup));}
function repoTakenLees(): array{return privateStoreLees('taken', 'takenLees');}
function repoTakenSchrijf(array $data, bool $backup=true): bool{return privateStoreSchrijf('taken', $data, static fn($d) => repoPhpJsonSchrijf(takenBestandPad(), TAKEN_VOORLOOP, $d, 'takenMaakBackup', $backup));}
function repoOperationeleTakenLees(): array{return privateStoreLees('operationele_taken', 'otakenLees');}
function repoOperationeleTakenSchrijf(array $data, bool $backup=true): bool{return privateStoreSchrijf('operationele_taken', $data, static fn($d) => repoPhpJsonSchrijf(otaakBestandPad(), OTAKEN_VOORLOOP, $d, 'otakenMaakBackup', $backup));}
function repoNormaliseerEvenementenDocument(array $data): array
{
    if ($data === []) return evenementenLeegBestand();
    if (!isset($data['evenementen']) || !is_array($data['evenementen'])) {
        throw new RuntimeException('Evenementenopslag heeft een ongeldige documentstructuur.');
    }
    $data['volgnummer'] = isset($data['volgnummer']) ? (int)$data['volgnummer'] : 0;
    return $data;
}
function repoEvenementenLees(): array{return repoNormaliseerEvenementenDocument(privateStoreLees('evenementen', 'evenementenLees'));}
function repoEvenementenSchrijf(array $data, bool $backup=true): bool{return privateStoreSchrijf('evenementen', $data, static fn($d) => repoPhpJsonSchrijf(evenementBestandPad(), EVENEMENTEN_VOORLOOP, $d, 'evenementenMaakBackup', $backup));}
function repoGroepenLees(): array{return privateStoreLees('groepen', 'groepenLees');}
function repoGroepenSchrijf(array $data, bool $backup=true): bool{return privateStoreSchrijf('groepen', $data, static fn($d) => repoPhpJsonSchrijf(groepenBestandPad(), GROEPEN_VOORLOOP, $d, 'groepenMaakBackup', $backup));}
function repoLedenlabelsLees(): array{return privateStoreLees('ledenlabels', 'ledenlabelsLees');}
function repoLedenlabelsSchrijf(array $data, bool $backup=true): bool{return privateStoreSchrijf('ledenlabels', $data, static fn($d) => repoPhpJsonSchrijf(ledenlabelsBestandPad(), LEDENLABELS_VOORLOOP, $d, 'ledenlabelsMaakBackup', $backup));}
