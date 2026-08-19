from pathlib import Path
import re

root = Path('.')
app = root / 'app'
app.mkdir(exist_ok=True)

moves = {
    root / 'auth-session-check.php': app / 'auth-session-check.php',
    root / 'data-slot.php': app / 'data-slot.php',
    root / 'paneel-hulp.php': app / 'paneel-hulp.php',
}

# Idempotent zodat een tweede deploy na de migratie niets opnieuw verandert.
for oud, nieuw in moves.items():
    if oud.exists() and not nieuw.exists():
        oud.rename(nieuw)
    elif oud.exists() and nieuw.exists():
        raise SystemExit(f'Zowel oud als nieuw bestaat: {oud} / {nieuw}')
    elif not nieuw.exists():
        raise SystemExit(f'Helper ontbreekt: {oud} en {nieuw}')

replacements = {
    root / 'auth.php': [
        ("require __DIR__ . '/auth-session-check.php';", "require __DIR__ . '/app/auth-session-check.php';"),
    ],
    root / 'leden-opslag.php': [
        ("require_once __DIR__ . '/data-slot.php';", "require_once __DIR__ . '/app/data-slot.php';"),
    ],
    root / 'beheer' / 'gebruikers.php': [
        ("require_once dirname(__DIR__) . '/data-slot.php';", "require_once dirname(__DIR__) . '/app/data-slot.php';"),
    ],
    root / 'beheer' / 'index.php': [
        ("require_once dirname(__DIR__) . '/paneel-hulp.php';", "require_once dirname(__DIR__) . '/app/paneel-hulp.php';"),
    ],
    root / 'leden-app.php': [
        ("require_once __DIR__ . '/paneel-hulp.php';", "require_once __DIR__ . '/app/paneel-hulp.php';"),
    ],
}

for pad, regels in replacements.items():
    tekst = pad.read_text(encoding='utf-8')
    for oud, nieuw in regels:
        if oud in tekst:
            tekst = tekst.replace(oud, nieuw)
        elif nieuw not in tekst:
            raise SystemExit(f'Verwachte include niet gevonden in {pad}: {oud}')
    pad.write_text(tekst, encoding='utf-8')

# Eén serverregel beschermt voortaan de hele interne app-map.
ht = root / '.htaccess'
h = ht.read_text(encoding='utf-8')
marker = "# ===== Interne applicatiecode =====\n"
if marker not in h:
    invoeg = """# ===== Interne applicatiecode =====
# Technische helpers in app/ zijn alleen voor PHP includes en nooit publieke
# endpoints. Eén mapregel vervangt losse bestandsblokkades.
RewriteRule ^app(?:/|$) - [F,L,NC]


"""
    doel = '# ===== Interne ledenapplicatie =====\n'
    if doel not in h:
        raise SystemExit('Invoegpunt voor app-afscherming niet gevonden')
    h = h.replace(doel, invoeg + doel, 1)

oud_regex = '<FilesMatch "^(auth\\.php|auth-session-check\\.php|paneel-hulp\\.php|data-slot\\.php|seo-head\\.php|gebruikers-rechten\\.php)$">'
nieuw_regex = '<FilesMatch "^(auth\\.php|seo-head\\.php|gebruikers-rechten\\.php)$">'
if oud_regex in h:
    h = h.replace(oud_regex, nieuw_regex)
elif nieuw_regex not in h:
    raise SystemExit('Interne FilesMatch-regel niet gevonden')
ht.write_text(h, encoding='utf-8')

# Geen oude includepaden meer toestaan.
controle = {
    "'/auth-session-check.php'": [root / 'auth.php'],
    "'/data-slot.php'": [root / 'leden-opslag.php', root / 'beheer' / 'gebruikers.php'],
    "'/paneel-hulp.php'": [root / 'beheer' / 'index.php', root / 'leden-app.php'],
}
for needle, bestanden in controle.items():
    for bestand in bestanden:
        tekst = bestand.read_text(encoding='utf-8')
        if needle in tekst and "'/app/" + needle[2:] not in tekst:
            raise SystemExit(f'Oud helperpad achtergebleven in {bestand}: {needle}')

print('Interne helpers verhuisd naar app/.')
