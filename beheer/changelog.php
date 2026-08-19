<?php
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/app/data-slot.php';
require_once dirname(__DIR__) . '/app/beheer/editor-hulp.php';

if (!$ingelogd) { header('Location: ../beheer.php'); exit; }
$rechten = authRechten(['changelog' => 'Changelog'], []);
if (!$isMaster && !in_array('changelog', $rechten['toegestaneTabs'] ?? [], true)) {
    http_response_code(403);
    echo 'Geen toegang tot Changelog.';
    exit;
}

$bestand = dirname(__DIR__) . '/data/changelog.json';
$vastPad = dirname(__DIR__) . '/changelog-historie.php';
$categorieen = [
    'nieuw' => 'Nieuw',
    'verbeterd' => 'Verbeterd',
    'opgelost' => 'Opgelost',
    'beveiliging' => 'Beveiliging',
    'onderhoud' => 'Onderhoud',
];

function clEigen(string $pad): array
{
    $data = beheerEditorLeesJson($pad, []);
    return array_values(array_filter($data, 'is_array'));
}

function clCategorie(string $categorie): string
{
    // In enkele vaste historische regels is eerder 'verbetering' gebruikt.
    // In eigen data blijft 'verbeterd' de canonieke sleutel.
    if ($categorie === 'verbetering') return 'verbeterd';
    return array_key_exists($categorie, $GLOBALS['categorieen']) ? $categorie : 'nieuw';
}

$melding = '';
$type = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrfOk()) {
        $melding = 'Sessie verlopen. Ververs de pagina en probeer opnieuw.';
        $type = 'fout';
    } else {
        $actie = (string) ($_POST['actie'] ?? '');
        $regels = clEigen($bestand);
        $slot = dataSlotOpen();
        try {
            $ok = false;
            $logDetail = '';

            if ($actie === 'toevoegen' || $actie === 'bewerken') {
                $datum = beheerEditorDatumIso($_POST['datum'] ?? '');
                if ($datum === '') $datum = date('Y-m-d');
                $cat = clCategorie((string) ($_POST['cat'] ?? 'nieuw'));
                $titel = beheerEditorKort($_POST['titel'] ?? '', 120);
                $tekst = beheerEditorKort($_POST['tekst'] ?? '', 500);

                if ($titel === '') {
                    $melding = 'Vul een korte omschrijving in.';
                    $type = 'fout';
                } elseif ($actie === 'toevoegen') {
                    try {
                        $suffix = bin2hex(random_bytes(3));
                    } catch (Throwable $e) {
                        $suffix = (string) mt_rand(100000, 999999);
                    }
                    array_unshift($regels, [
                        'id' => date('YmdHis') . '-' . $suffix,
                        'datum' => $datum,
                        'cat' => $cat,
                        'titel' => $titel,
                        'tekst' => $tekst,
                    ]);
                    $ok = beheerEditorSchrijfJson($bestand, $regels);
                    $logDetail = 'toegevoegd: ' . $titel;
                } else {
                    $id = (string) ($_POST['id'] ?? '');
                    $gevonden = false;
                    foreach ($regels as &$regel) {
                        if ((string) ($regel['id'] ?? '') !== $id) continue;
                        $regel = ['id' => $id, 'datum' => $datum, 'cat' => $cat, 'titel' => $titel, 'tekst' => $tekst];
                        $gevonden = true;
                        break;
                    }
                    unset($regel);
                    if (!$gevonden) {
                        $melding = 'Changelogregel niet gevonden.';
                        $type = 'fout';
                    } else {
                        $ok = beheerEditorSchrijfJson($bestand, $regels);
                        $logDetail = 'bijgewerkt: ' . $titel;
                    }
                }
            } elseif ($actie === 'verwijderen') {
                $id = (string) ($_POST['id'] ?? '');
                $voor = count($regels);
                $regels = array_values(array_filter($regels, static fn($regel) => (string) ($regel['id'] ?? '') !== $id));
                if (count($regels) === $voor) {
                    $melding = 'Changelogregel niet gevonden.';
                    $type = 'fout';
                } else {
                    $ok = beheerEditorSchrijfJson($bestand, $regels);
                    $logDetail = 'eigen regel verwijderd';
                }
            } else {
                $melding = 'Onbekende actie.';
                $type = 'fout';
            }

            if ($ok) {
                $melding = 'Changelog opgeslagen.';
                $type = 'ok';
                schrijfLog($logBestand, $huidigeGebruiker, 'changelog', $logDetail);
            } elseif ($melding === '') {
                $melding = 'Opslaan mislukt.';
                $type = 'fout';
            }
        } finally {
            dataSlotDicht($slot);
        }
    }
}

$eigen = clEigen($bestand);
$vast = is_file($vastPad) ? require $vastPad : [];
if (!is_array($vast)) $vast = [];
?><!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Changelog beheren</title>
<style>
body{margin:0;background:#f6f2e8;color:#26351d;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.top{background:#fff;border-bottom:1px solid #ddd8c0;padding:16px 24px}.topin,.wrap{max-width:1050px;margin:auto}.topin{display:flex;justify-content:space-between}.top a{color:#2d6260;text-decoration:none;font-weight:700}.wrap{padding:30px 24px 70px}.kaart{background:#fff;border:1px solid #ddd8c0;border-radius:14px;padding:20px;margin-bottom:16px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.veld{margin-bottom:12px}.veld label{display:block;font-weight:700;font-size:12px;margin-bottom:5px}.veld input,.veld select,.veld textarea,.filterbalk input,.filterbalk select{width:100%;box-sizing:border-box;border:1px solid #cfcab7;border-radius:8px;padding:10px;font:inherit}.veld textarea{min-height:90px}.melding{padding:12px 14px;border-radius:9px;margin-bottom:18px}.ok{background:#e8f5ee;color:#205b38}.fout{background:#fdeceb;color:#8b2e27}.btn{border:0;border-radius:8px;padding:9px 14px;font-weight:700;cursor:pointer;background:#3a7a77;color:#fff}.danger{background:#a23b32}.meta{color:#68705f;font-size:13px}.acties{display:flex;gap:8px}.filterbalk{display:grid;grid-template-columns:2fr 1fr;gap:12px;margin:18px 0}.badge{display:inline-block;border-radius:999px;padding:4px 9px;background:#eef1e8;color:#506044;font-size:12px;font-weight:700}.vast{border-left:4px solid #c7b36b}.changelog-regel[hidden]{display:none!important}.historie-titel{display:flex;gap:10px;align-items:center;flex-wrap:wrap}.historie-titel h3{margin:0}.historie-tekst{white-space:pre-line;line-height:1.5;margin-top:10px}@media(max-width:700px){.grid,.filterbalk{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="top"><div class="topin"><a href="./">← Terug naar beheer</a><span><?= count($eigen) ?> eigen · <?= count($vast) ?> vaste regels</span></div></div>
<main class="wrap">
<h1>Changelog</h1>
<p class="meta">Eigen verenigingsregels kun je bewerken. De ontwikkelaarshistorie uit de repository is zichtbaar maar alleen-lezen.</p>
<?php if ($melding !== ''): ?><div class="melding <?= beheerEditorEsc($type) ?>"><?= beheerEditorEsc($melding) ?></div><?php endif; ?>

<section class="kaart">
<h2>Regel toevoegen</h2>
<form method="post">
<input type="hidden" name="csrf" value="<?= beheerEditorEsc($csrfToken) ?>">
<input type="hidden" name="actie" value="toevoegen">
<div class="grid">
<div class="veld"><label>Datum</label><input name="datum" value="<?= date('d-m-Y') ?>"></div>
<div class="veld"><label>Categorie</label><select name="cat"><?php foreach ($categorieen as $k=>$v): ?><option value="<?= beheerEditorEsc($k) ?>"><?= beheerEditorEsc($v) ?></option><?php endforeach; ?></select></div>
</div>
<div class="veld"><label>Korte omschrijving</label><input name="titel" maxlength="120"></div>
<div class="veld"><label>Toelichting</label><textarea name="tekst" maxlength="500"></textarea></div>
<button class="btn" type="submit">Toevoegen</button>
</form>
</section>

<div class="filterbalk">
<input type="search" id="cl-zoek" placeholder="Zoeken in de hele changelog">
<select id="cl-cat"><option value="">Alle categorieën</option><?php foreach ($categorieen as $k=>$v): ?><option value="<?= beheerEditorEsc($k) ?>"><?= beheerEditorEsc($v) ?></option><?php endforeach; ?></select>
</div>

<h2>Eigen regels (<?= count($eigen) ?>)</h2>
<?php if (!$eigen): ?><p class="meta">Nog geen eigen regels.</p><?php endif; ?>
<?php foreach ($eigen as $regel):
    $id=(string)($regel['id']??'');
    $cat=clCategorie((string)($regel['cat']??'nieuw'));
    $zoek=strtolower((string)($regel['titel']??'').' '.(string)($regel['tekst']??''));
?>
<section class="kaart changelog-regel" data-cat="<?= beheerEditorEsc($cat) ?>" data-zoek="<?= beheerEditorEsc($zoek) ?>">
<form method="post">
<input type="hidden" name="csrf" value="<?= beheerEditorEsc($csrfToken) ?>">
<input type="hidden" name="id" value="<?= beheerEditorEsc($id) ?>">
<div class="grid">
<div class="veld"><label>Datum</label><input name="datum" value="<?= beheerEditorEsc(beheerEditorDatumNl($regel['datum']??'')) ?>"></div>
<div class="veld"><label>Categorie</label><select name="cat"><?php foreach ($categorieen as $k=>$v): ?><option value="<?= beheerEditorEsc($k) ?>"<?= $k===$cat?' selected':'' ?>><?= beheerEditorEsc($v) ?></option><?php endforeach; ?></select></div>
</div>
<div class="veld"><label>Korte omschrijving</label><input name="titel" maxlength="120" value="<?= beheerEditorEsc($regel['titel']??'') ?>"></div>
<div class="veld"><label>Toelichting</label><textarea name="tekst" maxlength="500"><?= beheerEditorEsc($regel['tekst']??'') ?></textarea></div>
<div class="acties"><button class="btn" name="actie" value="bewerken" type="submit">Opslaan</button><button class="btn danger" name="actie" value="verwijderen" type="submit" onclick="return confirm('Deze eigen changelogregel verwijderen?')">Verwijderen</button></div>
</form>
</section>
<?php endforeach; ?>

<h2>Vaste ontwikkelaarshistorie (<?= count($vast) ?>)</h2>
<?php foreach ($vast as $regel): if (!is_array($regel)) continue;
    $cat=clCategorie((string)($regel['cat']??'nieuw'));
    $zoek=strtolower((string)($regel['titel']??'').' '.(string)($regel['tekst']??''));
?>
<section class="kaart vast changelog-regel" data-cat="<?= beheerEditorEsc($cat) ?>" data-zoek="<?= beheerEditorEsc($zoek) ?>">
<div class="historie-titel"><h3><?= beheerEditorEsc($regel['titel']??'') ?></h3><span class="badge"><?= beheerEditorEsc($categorieen[$cat]??'Nieuw') ?></span><span class="meta"><?= beheerEditorEsc(beheerEditorDatumNl($regel['datum']??'')) ?></span></div>
<?php if (trim((string)($regel['tekst']??'')) !== ''): ?><div class="historie-tekst"><?= nl2br(beheerEditorEsc($regel['tekst'])) ?></div><?php endif; ?>
</section>
<?php endforeach; ?>
</main>
<script>
(function(){
  const zoek=document.getElementById('cl-zoek');
  const cat=document.getElementById('cl-cat');
  const regels=[...document.querySelectorAll('.changelog-regel')];
  function filter(){
    const q=(zoek.value||'').trim().toLowerCase();
    const c=cat.value||'';
    regels.forEach(function(regel){
      const pastZoek=!q||(regel.dataset.zoek||'').includes(q);
      const pastCat=!c||(regel.dataset.cat||'')===c;
      regel.hidden=!(pastZoek&&pastCat);
    });
  }
  zoek.addEventListener('input',filter);cat.addEventListener('change',filter);
})();
</script>
</body>
</html>
