from pathlib import Path

root = Path('.')

# Exact, intentionally small path fixes in public beheer editors.
for rel in ['beheer/agenda.php','beheer/sponsors.php','beheer/media.php','beheer/fotoboek.php']:
    p=root/rel
    s=p.read_text()
    old="require_once dirname(__DIR__) . '/data-slot.php';"
    assert old in s, f'missing legacy data-slot include in {rel}'
    s=s.replace(old,"require_once dirname(__DIR__) . '/app/data-slot.php';",1)
    if rel=='beheer/fotoboek.php':
        old2="require_once __DIR__ . '/fotoboek-lib.php';"
        assert old2 in s
        s=s.replace(old2,"require_once dirname(__DIR__) . '/app/beheer/fotoboek-lib.php';",1)
    p.write_text(s)

# Public modules use internal registries/rights from app/beheer.
repls={
 'beheer/backups.php':("$bestanden = require __DIR__ . '/backup-registry.php';","$bestanden = require dirname(__DIR__) . '/app/beheer/backup-registry.php';"),
 'beheer/gebruikers.php':("$rechtenDef = require __DIR__ . '/gebruikers-rechten.php';","$rechtenDef = require dirname(__DIR__) . '/app/beheer/gebruikers-rechten.php';"),
}
for rel,(old,new) in repls.items():
    p=root/rel; s=p.read_text(); assert old in s, rel; p.write_text(s.replace(old,new,1))

# Internal helper copies are already present under app/beheer; remove public originals only after callers are switched.
for rel in ['beheer/backup-registry.php','beheer/fotoboek-lib.php','beheer/gebruikers-rechten.php']:
    p=root/rel
    assert p.exists(), rel
    p.unlink()

# Old beheer bootstrap layer is superseded by app/beheer. Remove it only when new equivalents exist.
for rel in ['beheer/bootstrap.php','beheer/module-registry.php']:
    p=root/rel
    if p.exists(): p.unlink()
mods=root/'beheer/modules'
if mods.exists():
    for p in mods.glob('*.php'): p.unlink()
    try: mods.rmdir()
    except OSError: pass

# paneel-hulp must use the internal bootstrap.
p=root/'app/paneel-hulp.php'; s=p.read_text()
old="$projectRoot . '/beheer/bootstrap.php'"
assert old in s
p.write_text(s.replace(old,"$projectRoot . '/app/beheer/bootstrap.php'",1))

# Safety scan: no PHP file may still include the removed root data-slot or old beheer bootstrap/module layer.
needles=["'/data-slot.php'", '"/data-slot.php"', "'/beheer/bootstrap.php'", '"/beheer/bootstrap.php"']
for p in root.rglob('*.php'):
    if '.git' in p.parts: continue
    text=p.read_text(errors='ignore')
    for needle in needles:
        assert needle not in text, f'legacy include {needle} remains in {p}'

# Public beheer directory must contain entrypoints only, not include-only helpers/modules.
for forbidden in ['backup-registry.php','fotoboek-lib.php','gebruikers-rechten.php','bootstrap.php','module-registry.php']:
    assert not (root/'beheer'/forbidden).exists(), forbidden
assert not (root/'beheer/modules').exists()
