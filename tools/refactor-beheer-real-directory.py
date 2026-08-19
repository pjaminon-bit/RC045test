from pathlib import Path
import re

root = Path('.')
old = root / 'beheer.php'
target = root / 'beheer' / 'index.php'

# Idempotent: na een geslaagde eerste run is beheer.php alleen nog de
# compatibiliteitsredirect. Dan hoeft deze migratie niets meer te doen.
if target.exists() and old.exists() and 'Backwards-compatible ingang' in old.read_text(encoding='utf-8'):
    print('Beheer is al naar de echte map gemigreerd.')
    raise SystemExit(0)

if not old.exists():
    raise SystemExit('beheer.php ontbreekt')

s = old.read_text(encoding='utf-8')

# De volledige applicatie verhuist één directory dieper. beheer.php gebruikte
# __DIR__ steeds als site-root; vanuit beheer/index.php is dirname(__DIR__) die
# zelfde root. Op dit moment bevat beheer.php geen reeds-geneste dirname(__DIR__)
# constructies; dat wordt hieronder ook expliciet gevalideerd.
if 'dirname(__DIR__)' in s:
    raise SystemExit('Onverwachte dirname(__DIR__) in beheer.php; migratie eerst beoordelen.')
s = s.replace('__DIR__', 'dirname(__DIR__)')

# Post binnen dezelfde echte map. De hash is alleen browsernavigatie.
s = s.replace('action="beheer.php#', 'action="./#')
s = s.replace('action="beheer.php"', 'action="./"')
s = s.replace("header('Location: beheer.php#fotoboek');", "header('Location: ./#fotoboek');")

# Link vanuit de no-access melding en terug naar de publieke site.
s = s.replace('href="leden.php"', 'href="../leden/"')
s = s.replace('href="index.html"', 'href="../index.html"')

# Browser-assets leven in de installatie-root. Vanuit beheer/ is dat ../.
s = s.replace('href="favicon-32x32.png"', 'href="../favicon-32x32.png"')
s = s.replace('href="paneel.css?', 'href="../paneel.css?')
s = s.replace('src="paneel-thema.js?', 'src="../paneel-thema.js?')
s = s.replace('src="paneel.js?', 'src="../paneel.js?')
s = s.replace('src="images/', 'src="../images/')
s = s.replace("url('images/", "url('../images/")

# Modules staan fysiek naast index.php. Geen berekende installatiebasis meer.
s = re.sub(
    r"\n\s*// Basis-URL van deze installatie\.[^\n]*\n\s*// zodat dit zowel onder /dev als in de domeinroot werkt\.\n\s*\$beheerInstallatieBasis = [^;]+;",
    '',
    s,
)
s = s.replace(
    "'gebruikers' => ['label' => 'Gebruikers', 'href' => $beheerInstallatieBasis . '/beheer/gebruikers.php'],",
    "'gebruikers' => ['label' => 'Gebruikers', 'href' => 'gebruikers.php'],",
)
s = s.replace(
    "'log'        => ['label' => 'Logboek', 'href' => $beheerInstallatieBasis . '/beheer/logboek.php'],",
    "'log'        => ['label' => 'Logboek', 'href' => 'logboek.php'],",
)
s = s.replace(
    "'backups'    => ['label' => 'Back-ups', 'href' => $beheerInstallatieBasis . '/beheer/backups.php'],",
    "'backups'    => ['label' => 'Back-ups', 'href' => 'backups.php'],",
)

forbidden = [
    'action="beheer.php',
    "Location: beheer.php#fotoboek",
    '$beheerInstallatieBasis',
    'href="paneel.css',
    'src="paneel.js',
    'src="paneel-thema.js',
    'src="images/',
    "url('images/",
]
leftovers = [x for x in forbidden if x in s]
if leftovers:
    raise SystemExit('beheer/index.php bevat nog oude paden: ' + ', '.join(leftovers))

if "'href' => 'logboek.php'" not in s or 'action="./#homepage"' not in s:
    raise SystemExit('Verwachte nieuwe module/formulierpaden ontbreken.')

target.write_text(s, encoding='utf-8')

# Oude URL blijft bruikbaar. 308 behoudt de HTTP-methode en body, dus zelfs
# een oude bookmark/script dat nog POST naar beheer.php doet verliest geen data.
old.write_text("""<?php
// Backwards-compatible ingang. Het beheer is nu een echte map met index.php.
$script = str_replace('\\\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/beheer.php'));
$basis = rtrim(str_replace('\\\\', '/', dirname($script)), '/');
header('Location: ' . ($basis === '' ? '' : $basis) . '/beheer/', true, 308);
exit;
""", encoding='utf-8')

# paneel.js wordt ook buiten beheer gebruikt. Relatieve AJAX/vendor-paden
# baseren we daarom op de URL van paneel.js zelf. Zo werkt dezelfde JS in elke
# directory zonder rewrite-regels of hardcoded /dev.
js_path = root / 'paneel.js'
js = js_path.read_text(encoding='utf-8')
if 'var PANEEL_ROOT_URL' not in js:
    js = js.replace(
        "(function() {\n",
        "(function() {\n      var PANEEL_SCRIPT_URL = document.currentScript && document.currentScript.src ? document.currentScript.src : location.href;\n      var PANEEL_ROOT_URL = new URL('.', PANEEL_SCRIPT_URL);\n",
        1,
    )
js = js.replace("fetch('vertaal.php'", "fetch(new URL('vertaal.php', PANEEL_ROOT_URL)")
js = js.replace(
    "script.src = 'vendor/heic2any/heic2any.min.js';",
    "script.src = new URL('vendor/heic2any/heic2any.min.js', PANEEL_ROOT_URL).href;",
)
js_path.write_text(js, encoding='utf-8')

# Beheer heeft geen virtuele-routepleisters meer nodig. Leden is nog wel een
# virtuele route en behoudt voorlopig alleen zijn eigen expliciete fallbacks.
hp_path = root / '.htaccess'
hp = hp_path.read_text(encoding='utf-8')
start = hp.find('# ===== Virtuele routes: relatieve assets en publieke endpoints =====')
end = hp.find("# ===== Fotoboek: nette album-URL's =====", start)
if start < 0 or end < 0:
    raise SystemExit('Virtuele-routeblok in .htaccess niet gevonden.')
leden_block = """# ===== Virtuele ledenroute: relatieve assets en publieke endpoints =====
# /leden/ is voorlopig nog een interne rewrite naar leden.php. Alleen voor die
# legacy-route blijven expliciete publieke fallbacks nodig. Beheer is nu een
# echte directory en heeft deze regels niet meer nodig.
RewriteRule ^leden/(paneel\\.css|paneel\\.js|paneel-thema\\.js|favicon-32x32\\.png)$ $1 [L,QSA,NC]
RewriteRule ^leden/(vertaal\\.php)$ $1 [L,QSA,NC]
RewriteRule ^leden/(vendor/heic2any/heic2any\\.min\\.js)$ $1 [L,QSA,NC]
RewriteRule ^leden/leden\\.php$ leden.php [L,QSA,NC]


"""
hp = hp[:start] + leden_block + hp[end:]

# De /beheer/ rewrite is overbodig zodra beheer/index.php bestaat.
hp = re.sub(
    r"\n# ===== Beheer =====\n# Intern herschrijven\.[\s\S]*?RewriteRule \^beheer/\?\$ beheer\.php \[L\]\n",
    "\n",
    hp,
    count=1,
)
if 'RewriteRule ^beheer/?$ beheer.php' in hp or 'beheer/beheer/' in hp or 'beheer/beheer\\.php' in hp:
    raise SystemExit('Er staat nog een beheer-specifieke rewrite/fallback in .htaccess.')
hp_path.write_text(hp, encoding='utf-8')

print('Beheer is omgezet naar een echte directory-app.')
