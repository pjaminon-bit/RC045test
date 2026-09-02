<?php
$root = dirname(__DIR__);
require_once $root . '/app/deployment/authenticated-e2e-fixture.php';
require_once $root . '/app/deployment/authenticated-e2e-membership-fixture.php';

$ok = 0; $fout = 0;
function c159f(bool $conditie, string $label): void
{
    global $ok, $fout;
    if ($conditie) { $ok++; echo "OK: {$label}\n"; return; }
    $fout++; fwrite(STDERR, "FOUT: {$label}\n");
}
function rm159(string $pad): void
{
    if (!file_exists($pad) && !is_link($pad)) return;
    if (is_dir($pad) && !is_link($pad)) {
        foreach (scandir($pad) ?: [] as $naam) if ($naam !== '.' && $naam !== '..') rm159($pad . DIRECTORY_SEPARATOR . $naam);
        @rmdir($pad); return;
    }
    @unlink($pad);
}
function root159(): string
{
    $pad = sys_get_temp_dir() . '/vp-issue159-fixture-' . bin2hex(random_bytes(6));
    if (!mkdir($pad, 0750, true)) throw new RuntimeException('Tijdelijke testmap kon niet worden gemaakt.');
    return $pad;
}

$tenant = 'test';
$roots = [];
try {
    // Oorspronkelijk ontbrekend bestand moet na cleanup opnieuw ontbreken.
    $r = root159(); $roots[] = $r;
    e2e159MembershipFixtureApply($r, $tenant);
    $p = e2e159MembershipFixturePaths($r);
    $fixture = file_get_contents($p['content']);
    c159f(is_string($fixture) && e2e159MembershipContainsReserved($fixture), 'apply plaatst één herkenbaar synthetisch lidmaatschapstype');
    c159f(is_file($p['state']), 'apply bewaart duurzame herstelstate vóór cleanup');
    e2e159MembershipFixtureCleanup($r, $tenant);
    c159f(!file_exists($p['content']) && !file_exists($p['state']), 'cleanup herstelt oorspronkelijk ontbrekend contentbestand als afwezig');

    // Bestaande bytes moeten byte-voor-byte terugkomen, inclusief formatting.
    $r = root159(); $roots[] = $r; $p = e2e159MembershipFixturePaths($r);
    @mkdir($p['content_dir'], 0750, true);
    $original = "{\n  \"types\": [ { \"id\": \"bestaand\", \"label\": {\"nl\":\"Bestaand\"}, \"actief\": false } ],\n  \"custom\": \"behouden\"\n}\n";
    file_put_contents($p['content'], $original);
    e2e159MembershipFixtureApply($r, $tenant);
    $during = (string)file_get_contents($p['content']);
    c159f($during !== $original && str_contains($during, e2e159MembershipFixtureId()), 'apply behoudt bestaande documentinhoud en voegt tijdelijke fixture toe');
    e2e159MembershipFixtureCleanup($r, $tenant);
    c159f(file_get_contents($p['content']) === $original, 'cleanup herstelt bestaande lidmaatschapstypen byte-voor-byte exact');
    c159f(!file_exists($p['state']), 'herstelstate verdwijnt pas na succesvol exact herstel');

    // Snapshot vóór contentwrite: cleanup moet een onderbroken apply veilig herkennen.
    $r = root159(); $roots[] = $r; $p = e2e159MembershipFixturePaths($r);
    @mkdir($p['content_dir'], 0750, true);
    $original = "{\"types\":[],\"marker\":\"origineel\"}\n";
    file_put_contents($p['content'], $original);
    e2e159MembershipFixtureApply($r, $tenant);
    file_put_contents($p['content'], $original); // simuleert: state stond er, mutatie nog niet/meer actief
    e2e159MembershipFixtureCleanup($r, $tenant);
    c159f(file_get_contents($p['content']) === $original && !file_exists($p['state']), 'pre-cleanup herstelt veilig een onderbroken apply met reeds originele bytes');

    // Onverwachte tussentijdse adminwijziging mag nooit worden overschreven.
    $r = root159(); $roots[] = $r; $p = e2e159MembershipFixturePaths($r);
    @mkdir($p['content_dir'], 0750, true);
    $original = "{\"types\":[]}\n";
    file_put_contents($p['content'], $original);
    e2e159MembershipFixtureApply($r, $tenant);
    $concurrent = "{\"types\":[],\"concurrent\":true}\n";
    file_put_contents($p['content'], $concurrent);
    $weigerde = false;
    try { e2e159MembershipFixtureCleanup($r, $tenant); } catch (RuntimeException $e) { $weigerde = true; }
    c159f($weigerde, 'cleanup faalt gesloten bij onverwachte wijziging tijdens E2E');
    c159f(file_get_contents($p['content']) === $concurrent && is_file($p['state']), 'fail-closed cleanup overschrijft onverwachte tenantwijziging niet en bewaart herstelstate');

    // Gereserveerde sleutel mag nooit bestaande tenantinhoud vervangen.
    $r = root159(); $roots[] = $r; $p = e2e159MembershipFixturePaths($r);
    @mkdir($p['content_dir'], 0750, true);
    $collision = json_encode(['types'=>[['id'=>e2e159MembershipFixtureId(),'label'=>['nl'=>'Echt'],'actief'=>true]]], JSON_PRETTY_PRINT) . "\n";
    file_put_contents($p['content'], $collision);
    $weigerde = false;
    try { e2e159MembershipFixtureApply($r, $tenant); } catch (RuntimeException $e) { $weigerde = true; }
    c159f($weigerde && file_get_contents($p['content']) === $collision && !file_exists($p['state']), 'apply weigert gereserveerde type-ID zonder bestaande inhoud te muteren');

    $integration = (string)file_get_contents($root . '/app/deployment/authenticated-e2e-ephemeral.php');
    c159f(str_contains($integration, "authenticated-e2e-membership-fixture.php") && str_contains($integration, 'e2e159MembershipFixtureRegisterShutdown();'), 'bestaande e2e apply/cleanup lifecycle activeert de herstelbare public-contentfixture');
} finally {
    foreach ($roots as $r) rm159($r);
}

echo "Issue #159 E2E lidmaatschapstypefixture: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
