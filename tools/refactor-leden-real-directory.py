from pathlib import Path
import re

root = Path('.')
old = root / 'leden.php'
target = root / 'leden' / 'index.php'

# Idempotent: na succesvolle migratie is leden.php alleen nog een redirect.
if target.exists() and old.exists() and 'Backwards-compatible ingang' in old.read_text(encoding='utf-8'):
    print('Leden is al naar de echte map gemigreerd.')
    raise SystemExit(0)

if not old.exists():
    raise SystemExit('leden.php ontbreekt')

s = old.read_text(encoding='utf-8')
if 'dirname(__DIR__)' in s:
    raise SystemExit('Onverwachte dirname(__DIR__) in leden.php; migratie eerst beoordelen.')

# Server-side paden blijven naar de installatie-root wijzen.
s = s.replace('__DIR__', 'dirname(__DIR__)')

# Formulieren en PRG blijven binnen /leden/.
s = s.replace('action="leden.php#', 'action="./#')
s = s.replace('action="leden.php"', 'action="./"')
s = s.replace("header('Location: leden.php#", "header('Location: ./#")

# Links vanuit de ledenapp.
s = s.replace('href="beheer.php"', 'href="../beheer/"')
s = s.replace('href="index.html"', 'href="../index.html"')

# Browser-assets staan in de installatie-root.
s = s.replace('href="favicon-32x32.png"', 'href="../favicon-32x32.png"')
s = s.replace('href="paneel.css?', 'href="../paneel.css?')
s = s.replace('src="paneel-thema.js?', 'src="../paneel-thema.js?')
s = s.replace('src="paneel.js?', 'src="../paneel.js?')
s = s.replace('src="images/', 'src="../images/')
s = s.replace("url('images/", "url('../images/")

forbidden = [
    'action="leden.php',
    "Location: leden.php#",
    'href="beheer.php"',
    'href="paneel.css',
    'src="paneel.js',
    'src="paneel-thema.js',
    'src="images/',
    "url('images/",
]
leftovers = [x for x in forbidden if x in s]
if leftovers:
    raise SystemExit('leden/index.php bevat nog oude paden: ' + ', '.join(leftovers))

if 'action="./"' not in s or 'href="../beheer/"' not in s:
    raise SystemExit('Verwachte nieuwe ledenpaden ontbreken.')

target.parent.mkdir(parents=True, exist_ok=True)
target.write_text(s, encoding='utf-8')

# Oude bestands-URL blijft compatibel. 308 behoudt methode/body.
old.write_text("""<?php
// Backwards-compatible ingang. Het ledengedeelte is nu een echte map met index.php.
$script = str_replace('\\\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/leden.php'));
$basis = rtrim(str_replace('\\\\', '/', dirname($script)), '/');
header('Location: ' . ($basis === '' ? '' : $basis) . '/leden/', true, 308);
exit;
""", encoding='utf-8')

# Nu ook leden een echte map is, zijn er geen virtuele paneelroutes meer nodig.
hp_path = root / '.htaccess'
hp = hp_path.read_text(encoding='utf-8')
start = hp.find('# ===== Virtuele ledenroute: relatieve assets en publieke endpoints =====')
end = hp.find("# ===== Fotoboek: nette album-URL's =====", start)
if start < 0 or end < 0:
    raise SystemExit('Virtuele ledenroute in .htaccess niet gevonden.')
hp = hp[:start] + hp[end:]

# Eventuele losse vriendelijke /leden/ rewrite ook verwijderen.
hp = re.sub(
    r"\n# ===== Leden =====\n[\s\S]*?RewriteRule \^leden/\?\$ leden\.php \[L\]\n",
    "\n",
    hp,
    count=1,
)

for marker in [
    'RewriteRule ^leden/?$ leden.php',
    'RewriteRule ^leden/(paneel',
    'RewriteRule ^leden/(vertaal',
    'RewriteRule ^leden/(vendor',
    'RewriteRule ^leden/leden\\.php',
]:
    if marker in hp:
        raise SystemExit('Er staat nog een leden-specifieke rewrite/fallback in .htaccess: ' + marker)

hp_path.write_text(hp, encoding='utf-8')
print('Leden is omgezet naar een echte directory-app.')
