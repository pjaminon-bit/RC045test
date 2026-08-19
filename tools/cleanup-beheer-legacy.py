from pathlib import Path


def cut_between(text: str, start: str, end: str, replacement: str = '') -> str:
    a = text.find(start)
    if a < 0:
        raise SystemExit(f'start marker ontbreekt: {start[:80]}')
    b = text.find(end, a)
    if b < 0:
        raise SystemExit(f'end marker ontbreekt: {end[:80]}')
    return text[:a] + replacement + text[b:]


p = Path('beheer.php')
s = p.read_text(encoding='utf-8')
original = s

# Back-upcatalogus hoorde alleen bij het oude tabblad en de oude
# herstelhandler. Automatische snapshots blijven via maakDataBackup() bestaan.
s = cut_between(
    s,
    '// Alle bestanden die automatisch back-upt worden (zie maakDataBackup()),',
    '// Formaten voor het fotoboek:',
    '// Back-upoverzicht en herstel staan in beheer/backups.php.\n\n'
)

# Helper die uitsluitend door het oude back-uptabblad werd gebruikt.
s = cut_between(
    s,
    '// Geeft de beschikbare back-ups van precies één bestand terug (nieuwste',
    '// Leest de afmetingen van een sponsorlogo',
    '// Back-uplijsten worden door beheer/backups.php opgebouwd.\n\n'
)

# Oude module-handlers niet langer als POST-doel accepteren in beheer.php.
for line in [
    "  'gebruiker_toevoegen' => 'gebruikers',\n",
    "  'gebruiker_tabs_bijwerken' => 'gebruikers',\n",
    "  'gebruiker_verwijderen' => 'gebruikers',\n",
    "  'backup_herstellen' => 'backups',\n",
]:
    if line not in s:
        raise SystemExit(f'formuliermapping ontbreekt: {line.strip()}')
    s = s.replace(line, '', 1)

# Oude gebruikers- en herstel-elseif-keten aan het eind van de beheer-POST-flow.
s = cut_between(
    s,
    "  } elseif ($formulier === 'gebruiker_toevoegen') {",
    '  // De opslag-acties van de ledenadministratie, commissies, vergaderingen,',
    '  // Gebruikersbeheer en back-upherstel staan in zelfstandige modules.\n\n'
)

old_load = """$gebruikersLijst = in_array('gebruikers', $toegestaneTabs, true) ? laadGebruikers($usersBestand) : [];
$logRegels = [];
if (in_array('log', $toegestaneTabs, true) && file_exists($logBestand)) {
  $json = json_decode(file_get_contents($logBestand), true);
  if (is_array($json)) $logRegels = array_reverse($json);
}
"""
if old_load not in s:
    raise SystemExit('oude gebruikers/log data-load ontbreekt')
s = s.replace(old_load, '', 1)

# Oude UI-blokken Gebruikers + Logboek + Back-ups zijn aaneengesloten.
s = cut_between(
    s,
    "    <?php if (in_array('gebruikers', $toegestaneTabs, true)): ?>",
    "    <?php if (in_array('rekentabel', $toegestaneTabs, true)): ?>",
    ''
)

# In het lokale tabmenu blijft alleen Changelog een tab. De drie gemigreerde
# onderdelen worden echte links naar de zelfstandige modules.
old_group = "['label' => 'Beheer', 'tabs' => ['changelog', 'gebruikers', 'log', 'backups']],"
new_group = "['label' => 'Beheer', 'tabs' => ['changelog']],"
if old_group not in s:
    raise SystemExit('oude Beheer-menugroep ontbreekt')
s = s.replace(old_group, new_group, 1)

old_visible = """          $zichtbaar = [];
          foreach ($groep['tabs'] as $tabSleutel) {
            if (in_array($tabSleutel, $toegestaneTabs, true)) $zichtbaar[] = $tabSleutel;
          }
        ?>
        <?php if (!empty($zichtbaar)): ?>
"""
new_visible = """          $zichtbaar = [];
          foreach ($groep['tabs'] as $tabSleutel) {
            if (in_array($tabSleutel, $toegestaneTabs, true)) $zichtbaar[] = $tabSleutel;
          }
          $zichtbareModuleLinks = [];
          if ($groep['label'] === 'Beheer') {
            $moduleLinks = [
              'gebruikers' => ['label' => 'Gebruikers', 'href' => 'beheer/gebruikers.php'],
              'log'        => ['label' => 'Logboek', 'href' => 'beheer/logboek.php'],
              'backups'    => ['label' => 'Back-ups', 'href' => 'beheer/backups.php'],
            ];
            foreach ($moduleLinks as $moduleSleutel => $moduleInfo) {
              if ($isMaster || in_array($moduleSleutel, $toegestaneTabs, true)) {
                $zichtbareModuleLinks[$moduleSleutel] = $moduleInfo;
              }
            }
          }
        ?>
        <?php if (!empty($zichtbaar) || !empty($zichtbareModuleLinks)): ?>
"""
if old_visible not in s:
    raise SystemExit('menu zichtbaarheidspatroon ontbreekt')
s = s.replace(old_visible, new_visible, 1)

old_buttons = """          <?php foreach ($zichtbaar as $tabSleutel): ?>
      <button type=\"button\" class=\"menu-item\" data-tab=\"<?php echo $tabSleutel; ?>\"><?php echo htmlspecialchars($menuLabels[$tabSleutel]); ?></button>
          <?php endforeach; ?>
"""
new_buttons = old_buttons + """          <?php foreach ($zichtbareModuleLinks as $moduleInfo): ?>
      <a class=\"menu-module-link\" href=\"<?php echo htmlspecialchars($moduleInfo['href']); ?>\"><?php echo htmlspecialchars($moduleInfo['label']); ?></a>
          <?php endforeach; ?>
"""
if old_buttons not in s:
    raise SystemExit('menu-knoppenpatroon ontbreekt')
s = s.replace(old_buttons, new_buttons, 1)

s = s.replace(
    '// Gebruikers, Log en Back-ups waren tot nu toe alleen bereikbaar met het\n// beheerderswachtwoord en zijn nu een vinkje net als de rest.',
    '// Gebruikers, Log en Back-ups zijn zelfstandige beheermodules. Hun\n// rechten blijven hier onderdeel van dezelfde centrale toegangslijst.',
    1
)

if s == original:
    raise SystemExit('beheer.php is niet gewijzigd')

forbidden = [
    "formulier === 'gebruiker_toevoegen'",
    "formulier === 'gebruiker_tabs_bijwerken'",
    "formulier === 'gebruiker_verwijderen'",
    "formulier === 'backup_herstellen'",
    'id="tab-gebruikers"',
    'id="tab-log"',
    'id="tab-backups"',
    '$gebruikersLijst',
    '$logRegels',
    'lijstDataBackups(',
    '$dataBackupBestanden',
]
remaining = [x for x in forbidden if x in s]
if remaining:
    raise SystemExit('legacy resten gevonden: ' + ', '.join(remaining))

p.write_text(s, encoding='utf-8')

# Modulelinks dezelfde visuele taal geven als tabknoppen, maar bewust zonder
# .menu-item zodat paneel.js ze niet als tab behandelt.
css = Path('paneel.css')
c = css.read_text(encoding='utf-8')
marker = "    .menu-item:hover { background: var(--teal-light); color: var(--teal-dark); }\n"
addition = """    .menu-module-link { width: 100%; display: block; text-align: left; flex: 0 0 auto; background: none; border: none; padding: 8px 12px; font-size: 13px; font-weight: 500; color: var(--text); text-decoration: none; cursor: pointer; border-radius: 8px; transition: background 0.15s, color 0.15s; }
    .menu-module-link:hover, .menu-module-link:focus-visible { background: var(--teal-light); color: var(--teal-dark); outline: none; }
"""
if '.menu-module-link {' not in c:
    if marker not in c:
        raise SystemExit('CSS menu-marker ontbreekt')
    c = c.replace(marker, marker + addition, 1)
    css.write_text(c, encoding='utf-8')

# Multiselect was voor de oude gebruikers-tab. Alleen verwijderen als geen
# andere PHP/HTML-pagina hem nog gebruikt.
users = []
for f in Path('.').rglob('*'):
    if not f.is_file() or f.name in {'paneel.js', 'paneel.css'} or '.git' in f.parts:
        continue
    if f.suffix.lower() not in {'.php', '.html'}:
        continue
    try:
        txt = f.read_text(encoding='utf-8')
    except Exception:
        continue
    if 'multiselect' in txt:
        users.append(str(f))

if not users:
    js = Path('paneel.js')
    j = js.read_text(encoding='utf-8')
    a = j.find('    // ===== Multiselect (dropdown met zoekvak en vinkjes) =====')
    b = j.find('    // ===== Fotoboek:', a)
    if a >= 0 and b > a:
        j = j[:a] + j[b:]
        js.write_text(j, encoding='utf-8')

print('beheer.php legacy cleanup uitgevoerd')
print('eventuele resterende multiselect-gebruikers:', users)
