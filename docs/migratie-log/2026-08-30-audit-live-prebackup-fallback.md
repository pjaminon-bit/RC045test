# Audit live finding: private-store prewrite fallback

Datum: 30 augustus 2026

Na merge van audit-hardening PR #113 was de volledige bronregressie groen en werd commit `319122a0e4f18e36da73f0cdcbf60163ae193053` succesvol als immutable release op VPS-test geactiveerd. De daaropvolgende authenticated E2E stopte fail-closed bij de eerste bestaande private PDO-collectie (`leden`), omdat de normale tenantbackupnamespace geen nieuwe pre-backup kon opslaan.

De beveiligingsregel zelf blijft ongewijzigd: bestaande private data mag nooit worden overschreven zonder een aantoonbaar duurzame herstelroute. De hotfix voegt daarom geen bypass toe, maar een tweede tenant-lokale prewrite-journalnamespace onder `private/backups/prewrite-v2/`. Deze route wordt uitsluitend gebruikt als `tenantBackupMaakArray()` geen normale snapshot kan plaatsen.

De fallback:

- blijft volledig binnen de fysieke `private_root`;
- weigert symlinkcomponenten fail-closed;
- bindt iedere snapshot aan tenant-key en backup-key;
- bevat een SHA-256-binding van de oude payload;
- schrijft via een exclusief tijdelijk bestand, flush/`fsync`, mode `0640` en atomische rename;
- controleert de geplaatste bytes opnieuw;
- laat de bestaande `backups/tenant` namespace ongemoeid;
- begrenst de noodjournal tot maximaal 200 snapshots per backup-key;
- geeft `null` terug bij iedere onzekere toestand, waarna de private-store write nog steeds hard stopt.

De gewone tenantbackup blijft altijd de eerste route. De prewrite-journal is uitsluitend een compatibiliteits-/herstelroute voor bestaande installaties met legacy filesystemdrift en geen vervanging voor de normale backup- en restorefunctionaliteit.
