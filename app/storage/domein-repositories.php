<?php
// ============================================================
// Private domeinrepositories
// ============================================================
// Controllers gebruiken deze functies; de huidige JSON/PHP-bestanden zijn
// alleen nog de fallback-backend. Bij private_driver=pdo worden dezelfde
// domeindocumenten per tenant transactioneel in de database opgeslagen.
// ============================================================
require_once __DIR__ . '/private-store.php';
require_once dirname(__DIR__,2) . '/leden-opslag.php';
require_once dirname(__DIR__,2) . '/vergaderingen-opslag.php';
require_once dirname(__DIR__,2) . '/taken-opslag.php';
require_once dirname(__DIR__,2) . '/operationele-taken-opslag.php';
require_once dirname(__DIR__,2) . '/evenementen-opslag.php';

function repoLedenLees(): array { return privateStoreLees('leden', 'ledenLees'); }
function repoLedenSchrijf(array $data,bool $backup=true): bool { return privateStoreSchrijf('leden',$data,static fn($d)=>ledenSchrijf($d,$backup)); }
function repoVergaderingenLees(): array { return privateStoreLees('vergaderingen','vergaderingenLees'); }
function repoVergaderingenSchrijf(array $data,bool $backup=true): bool { return privateStoreSchrijf('vergaderingen',$data,static fn($d)=>vergaderingenSchrijf($d,$backup)); }
function repoTakenLees(): array { return privateStoreLees('taken','takenLees'); }
function repoTakenSchrijf(array $data,bool $backup=true): bool { return privateStoreSchrijf('taken',$data,static fn($d)=>takenSchrijf($d,$backup)); }
function repoOperationeleTakenLees(): array { return privateStoreLees('operationele_taken','otakenLees'); }
function repoOperationeleTakenSchrijf(array $data,bool $backup=true): bool { return privateStoreSchrijf('operationele_taken',$data,static fn($d)=>otakenSchrijf($d,$backup)); }
function repoEvenementenLees(): array { return privateStoreLees('evenementen','evenementenLees'); }
function repoEvenementenSchrijf(array $data,bool $backup=true): bool { return privateStoreSchrijf('evenementen',$data,static fn($d)=>evenementenSchrijf($d,$backup)); }
