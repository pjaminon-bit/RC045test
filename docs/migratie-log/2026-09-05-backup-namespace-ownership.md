# #148 live finding: backupnamespace ownershipdrift

Datum: 5 september 2026

Tijdens de actieve cryptografische VPS-testacceptatie van #148 bleek de root-attestor correct actief en de publieke verificatiesleutel leesbaar voor de echte tenant-FPM-user. De eerste schema-2 data-backup werd echter vóór signing fail-closed afgebroken.

De live filesystemdiagnose wees de oorzaak aan:

- `/srv/verenigingen/test/private` was correct tenant-owned `0750`;
- `/srv/verenigingen/test/private/backups` was correct tenant-owned `0750`;
- de historische subboom `private/backups/tenant` en `private/backups/tenant/records` was `root:root 0750`;
- de tenant-FPM-user kon daardoor de recordsnamespace niet schrijven.

Dit is legacy filesystemdrift en geen gewenste trust boundary. Het bestaande runtimecontract bepaalt al dat `private_root` en runtime-data daaronder eigendom zijn van de unieke tenant-UID/GID. De root-attestor zelf heeft `/srv/verenigingen` read-only en kan deze drift niet veroorzaken of herstellen.

## Migratiecontract

`ops/vps-test-deploy/install-backup-attestation` herstelt bij root-only activatie uitsluitend de bekende `private/backups/tenant` namespace van geldige deployments.

De migratie:

1. vertrouwt alleen een regulier, root-owned en niet group/world-writeable `deployment.json`;
2. leidt tenant-root, private-root en de unieke tenant-UID/GID uit die deployment af;
3. vereist dat `private_root` en `private/backups` al correct tenant-owned `0750` zijn;
4. raakt niets aan wanneer `private/backups/tenant` niet bestaat;
5. valideert een gezonde tenant-owned backupboom zonder hem te herschrijven;
6. migreert alleen wanneer de backupnamespace-root exact `root:root` en niet group/world-writeable is;
7. weigert symlinks, special files, hardlinks, onverwachte owners en group/world-writeable objecten;
8. bindt mutaties aan vooraf gecontroleerde inode-identiteit via file descriptors en `O_NOFOLLOW`;
9. herstelt descendants deepest-first en maakt de backupnamespace-root pas als laatste tenant-owned, zodat de tenant-runtime tijdens de migratie geen half-herstelbare boom kan wijzigen;
10. normaliseert backupdirectories naar `0750` en reguliere backupbestanden naar `0640`;
11. laat `--check` na migratie dezelfde ownershipcontracten bewaken.

Er wordt bewust geen brede `chown -R` over `private_root` uitgevoerd. Onverwachte drift buiten deze historische backupnamespace blijft een aparte fout die door de bestaande runtime/provisioningcontracten moet worden onderzocht.

## Live acceptatie na merge

Na normale PR-CI, merge en VPS-testdeploy wordt de root-only installer opnieuw uitgevoerd vanuit een exact geverifieerde checkout van de gemergde release. Vereist bewijs:

- `backup_namespace_repaired=1` voor de bestaande VPS-testdrift;
- aansluitende `--check` met `backup_namespace_repaired=0` en een geldig ownershipcontract;
- tenant-FPM-user kan de recordsnamespace schrijven;
- nieuwe data- en assetsnapshots zijn schema 2 en hebben geldige detached attestaties;
- payload-, binding-, ontbrekende-signature-, legacy-, assetpayload-, manifest- en stagingtamper worden vóór restore/swap geweigerd;
- tijdelijke acceptance-artifacts zijn volledig opgeruimd;
- normale actieve-crypto VPS-deploy en post-deploy source/live-security/live-browser regressies blijven groen.
