<?php
require_once dirname(__DIR__) . '/leden/groepen.php';

function lidGroepenMagType(string $type): bool
{
    if ($type === 'commissie') return authHeeftCapability('committees.manage');
    if ($type === 'werkgroep') return authHeeftCapability('workgroups.manage');
    return false;
}

function lidGroepenVindLid(array $data, string $lidId): ?array
{
    foreach ((array)($data['leden'] ?? []) as $lid) {
        if (is_array($lid) && (string)($lid['id'] ?? '') === $lidId) return $lid;
    }
    return null;
}

function lidGroepenVerwerkPost(string $lidId, string $huidigeGebruiker, string $logBestand): array
{
    if ($lidId === '') return [false, 'Lid ontbreekt.'];
    $leden = ledenServiceLees();
    $lid = lidGroepenVindLid($leden, $lidId);
    if (!$lid) return [false, 'Lid niet gevonden.'];
    if (!empty($lid['gearchiveerd_op'])) return [false, 'Groepen van een gearchiveerd lid zijn alleen-lezen.'];

    $doc = groepenLeesDocument();
    $rolMap = groepenRolMap($doc, false);
    $gekozen = array_fill_keys(array_map('strval', (array)($_POST['groepen'] ?? [])), true);
    $postRollen = (array)($_POST['groepsrollen'] ?? []);
    $aangepast = 0;

    foreach ((array)($doc['groepen'] ?? []) as $i => $groep) {
        if (!is_array($groep) || !lidGroepenMagType((string)($groep['type'] ?? ''))) continue;
        if (($groep['status'] ?? 'actief') !== 'actief') continue;
        $groepId = (string)($groep['id'] ?? '');
        if ($groepId === '') continue;
        $rollen = [];
        foreach ((array)($postRollen[$groepId] ?? []) as $rolId) {
            $rolId = groepenId($rolId);
            if ($rolId !== '' && isset($rolMap[$rolId])) $rollen[] = $rolId;
        }
        if (!$rollen) $rollen = ['lid'];
        $voor = (array)($doc['groepen'][$i]['leden'] ?? []);
        $doc['groepen'][$i]['leden'] = groepenWerkLidBij($voor, $lidId, isset($gekozen[$groepId]), $rollen);
        if ($doc['groepen'][$i]['leden'] !== $voor) {
            $doc['groepen'][$i]['gewijzigd'] = date('c');
            $aangepast++;
        }
    }

    if ($aangepast === 0) return [true, 'Groepskoppelingen waren al actueel.'];
    if (!groepenSchrijfDocument($doc)) return [false, 'Groepskoppelingen konden niet worden opgeslagen.'];
    schrijfLog($logBestand, $huidigeGebruiker, 'lid_groepen_bijgewerkt', $lidId . ' (' . $aangepast . ')');
    return [true, 'Commissies en werkgroepen bijgewerkt.'];
}

function lidGroepenRender(array $lid): void
{
    $lidId = (string)($lid['id'] ?? '');
    if ($lidId === '') return;
    $doc = groepenLeesDocument();
    $rolMap = groepenRolMap($doc, false);
    $groepen = [];
    foreach ((array)($doc['groepen'] ?? []) as $groep) {
        if (!is_array($groep) || !lidGroepenMagType((string)($groep['type'] ?? ''))) continue;
        if (($groep['status'] ?? 'actief') !== 'actief') continue;
        $groepen[] = $groep;
    }
    usort($groepen, static function ($a, $b) {
        return strcmp((string)($a['type'] ?? ''), (string)($b['type'] ?? '')) ?: strnatcasecmp((string)($a['naam'] ?? ''), (string)($b['naam'] ?? ''));
    });
    $archief = !empty($lid['gearchiveerd_op']);
    ?>
    <div id="groepen" style="border-top:1px solid #eee9db;margin-top:20px;padding-top:18px">
      <h3 style="margin-top:0">Commissies en werkgroepen</h3>
      <p class="meta">Beheer hier direct de groepen en rollen van dit lid. Verwijderen uit een groep sluit de deelnamehistorie met de datum van vandaag.</p>
      <?php if (!$groepen): ?>
        <p class="meta">Er zijn geen actieve commissies of werkgroepen waarvoor je beheerrecht hebt.</p>
      <?php elseif ($archief): ?>
        <p class="notice">Dit lid is gearchiveerd. Groepshistorie blijft bewaard en kan hier niet worden gewijzigd.</p>
        <?php foreach ($groepen as $groep):
          $actief = null; foreach (groepenActieveLeden($groep) as $m) if (($m['lid_id'] ?? '') === $lidId) { $actief = $m; break; }
          if (!$actief) continue; ?>
          <p><strong><?=lmEsc($groep['naam'] ?? '')?></strong> <span class="meta">· <?=lmEsc(groepenTypes()[$groep['type']] ?? $groep['type'])?> · <?=lmEsc(implode(', ', array_map(static fn($r) => $rolMap[$r] ?? $r, (array)($actief['rollen'] ?? []))))?></span></p>
        <?php endforeach; ?>
      <?php else: ?>
        <form method="post">
          <input type="hidden" name="csrf" value="<?=lmEsc($GLOBALS['csrfToken'] ?? '')?>">
          <input type="hidden" name="actie" value="groepen_opslaan">
          <input type="hidden" name="id" value="<?=lmEsc($lidId)?>">
          <?php foreach ($groepen as $groep):
            $groepId = (string)($groep['id'] ?? '');
            $actief = null; foreach (groepenActieveLeden($groep) as $m) if (($m['lid_id'] ?? '') === $lidId) { $actief = $m; break; }
          ?>
            <div class="item" style="padding:10px 0">
              <label style="font-weight:700"><input type="checkbox" name="groepen[]" value="<?=lmEsc($groepId)?>" <?=$actief?'checked':''?>> <?=lmEsc($groep['naam'] ?? '')?></label>
              <span class="meta"> · <?=lmEsc(groepenTypes()[$groep['type']] ?? $groep['type'])?></span>
              <div class="actions" style="margin:7px 0 0 24px">
                <?php foreach ($rolMap as $rolId => $rolNaam): ?>
                  <label class="meta"><input type="checkbox" name="groepsrollen[<?=lmEsc($groepId)?>][]" value="<?=lmEsc($rolId)?>" <?=$actief&&in_array($rolId,(array)($actief['rollen']??[]),true)?'checked':''?>> <?=lmEsc($rolNaam)?></label>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
          <p><button class="btn primary" type="submit">Groepskoppelingen opslaan</button></p>
        </form>
      <?php endif; ?>
    </div>
    <?php
}
