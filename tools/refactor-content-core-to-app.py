from pathlib import Path

root = Path('.')
app = root / 'app' / 'content'
app.mkdir(parents=True, exist_ok=True)

# Kernimplementaties vanuit de root definitief naar app/content brengen.
content_src = root / 'content-pagina.php'
seo_src = root / 'seo-head.php'
if not content_src.exists() or not seo_src.exists():
    print('Contentkern is al gemigreerd; niets te doen.')
    raise SystemExit(0)

content = content_src.read_text(encoding='utf-8')
content = content.replace("return __DIR__ . '/' . ltrim($relatief, '/');", "return dirname(__DIR__, 2) . '/' . ltrim($relatief, '/');")
content = content.replace("$pad = __DIR__ . '/homepage.js';", "$pad = dirname(__DIR__, 2) . '/homepage.js';")
if "dirname(__DIR__, 2) . '/' . ltrim($relatief, '/')" not in content:
    raise SystemExit('Datapad in content-pagina.php kon niet veilig worden omgezet.')
if "dirname(__DIR__, 2) . '/homepage.js'" not in content:
    raise SystemExit('Homepage-JS-pad in content-pagina.php kon niet veilig worden omgezet.')
(app / 'content-pagina.php').write_text(content, encoding='utf-8')

seo = seo_src.read_text(encoding='utf-8')
seo = seo.replace("require_once __DIR__ . '/site.php';", "require_once dirname(__DIR__, 2) . '/site.php';")
seo = seo.replace("$RC045_PAGINAS = require __DIR__ . '/site-seo.php';", "$RC045_PAGINAS = require dirname(__DIR__, 2) . '/site-seo.php';")
if "dirname(__DIR__, 2) . '/site.php'" not in seo or "dirname(__DIR__, 2) . '/site-seo.php'" not in seo:
    raise SystemExit('Rootpaden in seo-head.php konden niet veilig worden omgezet.')
(app / 'seo-head.php').write_text(seo, encoding='utf-8')

# Alle aanroepers rechtstreeks naar de interne contentlaag laten wijzen.
vervangingen = {
    "__DIR__ . '/seo-head.php'": "__DIR__ . '/app/content/seo-head.php'",
    "__DIR__ . '/content-pagina.php'": "__DIR__ . '/app/content/content-pagina.php'",
    "__DIR__ . '/content-pagina-runtime.php'": "__DIR__ . '/app/content/content-pagina-runtime.php'",
    "__DIR__ . '/pagina-definities.php'": "__DIR__ . '/app/content/pagina-definities.php'",
    "dirname(__DIR__) . '/seo-head.php'": "dirname(__DIR__) . '/app/content/seo-head.php'",
    "dirname(__DIR__) . '/content-pagina.php'": "dirname(__DIR__) . '/app/content/content-pagina.php'",
    "dirname(__DIR__) . '/content-pagina-runtime.php'": "dirname(__DIR__) . '/app/content/content-pagina-runtime.php'",
    "dirname(__DIR__) . '/pagina-definities.php'": "dirname(__DIR__) . '/app/content/pagina-definities.php'",
}

for pad in root.rglob('*.php'):
    if 'app' in pad.parts or '.git' in pad.parts:
        continue
    tekst = pad.read_text(encoding='utf-8')
    nieuw = tekst
    for oud, vervanging in vervangingen.items():
        nieuw = nieuw.replace(oud, vervanging)
    if nieuw != tekst:
        pad.write_text(nieuw, encoding='utf-8')

# De vier laatste root-loaders/-implementaties zijn nu overbodig.
for naam in ['content-pagina.php', 'seo-head.php', 'pagina-definities.php', 'content-pagina-runtime.php']:
    pad = root / naam
    if pad.exists():
        pad.unlink()

# De app-map is al centraal geblokkeerd; verdwenen rootbestanden hoeven niet
# langer in de losse FilesMatch-lijst te staan.
ht = (root / '.htaccess').read_text(encoding='utf-8')
for naam in ['seo-head\\.php|', 'pagina-definities\\.php|', 'content-pagina\\.php|', 'content-pagina-runtime\\.php|']:
    ht = ht.replace(naam, '')
(root / '.htaccess').write_text(ht, encoding='utf-8')

# Fail closed wanneer ergens buiten app/ toch nog een directe include naar
# een van de verwijderde rootbestanden achterblijft.
verboden = [
    "__DIR__ . '/seo-head.php'",
    "__DIR__ . '/content-pagina.php'",
    "__DIR__ . '/content-pagina-runtime.php'",
    "__DIR__ . '/pagina-definities.php'",
    "dirname(__DIR__) . '/seo-head.php'",
    "dirname(__DIR__) . '/content-pagina.php'",
    "dirname(__DIR__) . '/content-pagina-runtime.php'",
    "dirname(__DIR__) . '/pagina-definities.php'",
]
resten = []
for pad in root.rglob('*.php'):
    if 'app' in pad.parts or '.git' in pad.parts:
        continue
    tekst = pad.read_text(encoding='utf-8')
    for patroon in verboden:
        if patroon in tekst:
            resten.append(f'{pad}: {patroon}')
if resten:
    raise SystemExit('Oude content-rootincludes gevonden:\n' + '\n'.join(resten))

print('Contentkern volledig naar app/content gemigreerd.')
