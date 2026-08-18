<?php
// ============================================================
// Beheermodule: Fotoboek
// ============================================================
// Schakelt de historische Fotoboek-tab en oude POST-routes uit en laat het
// bestaande beheer-menu naar de zelfstandige editor wijzen.
// ============================================================

function beheerFotoboekBewaakLegacyPost(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return;
    $formulier = isset($_POST['formulier']) && is_string($_POST['formulier']) ? $_POST['formulier'] : '';
    if (!in_array($formulier, ['fotoboek_tekst','fotoboek_album_aanmaken','fotoboek_album_bewerken'], true)) return;
    $_POST['formulier'] = '';
    if (function_exists('schrijfLog') && isset($GLOBALS['logBestand'], $GLOBALS['huidigeGebruiker'])) {
        schrijfLog($GLOBALS['logBestand'], (string)$GLOBALS['huidigeGebruiker'], 'legacy_fotoboek_geblokkeerd', $formulier);
    }
}
function beheerFotoboekMagOpenen(): bool
{
    if (empty($GLOBALS['ingelogd'])) return false;
    if (!empty($GLOBALS['isMaster'])) return true;
    $tabs = isset($GLOBALS['toegestaneTabs']) && is_array($GLOBALS['toegestaneTabs']) ? $GLOBALS['toegestaneTabs'] : [];
    return in_array('fotoboek', $tabs, true);
}
function beheerFotoboekStartOutputFilter(): void
{
    ob_start(function($html){
        if(!is_string($html)) return $html;
        if(beheerFotoboekMagOpenen()){
            $html=preg_replace('~<button\s+type="button"\s+class="menu-item"\s+data-tab="fotoboek">.*?</button>~is','<a class="menu-item menu-item-link" style="display:block;text-decoration:none" href="beheer/fotoboek.php">Fotoboek</a>',$html,1)??$html;
        }
        if(stripos($html,'</head>')!==false){$css='<style id="beheer-fotoboek-legacy-hidden">#tab-fotoboek,[href="#tab-fotoboek"],[href="#fotoboek"],[data-tab-target="fotoboek"]{display:none!important}</style>';$html=preg_replace('~</head>~i',$css."\n</head>",$html,1)??$html;}
        return $html;
    });
}
beheerFotoboekBewaakLegacyPost();
beheerFotoboekStartOutputFilter();
