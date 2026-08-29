# Platformbeheer: nieuwe vereniging — fase 5.7

Fase 5.7 voegt aan het bestaande VPS-platformbeheer de actie **Nieuwe vereniging** toe. De eerste implementatie is bewust een beperkte, veilige provisioningstap: de browser kan de tenantbasis laten aanmaken, maar kan geen rootcommando's, serverpaden of beheerderswachtwoorden aanleveren.

## Wat de GUI aanmaakt

De platformoperator vult in:

- verenigingsnaam;
- permanente technische tenant-key;
- canonieke productiehost;
- actieve platformmodules.

`Website` is altijd verplicht. De overige modulekeuzes komen uit dezelfde vaste platformlijst als `bin/provision-tenant.php`.

De aanvraag wordt als een strikt `5.1-request` met actie `provision` in de bestaande control-plane queue geplaatst. De queue bevat alleen functionele metadata. Er staan geen wachtwoorden, secrets, shellcommando's, argv-fragmenten of door de browser gekozen filesystempaden in.

## Securitygrens

De bestaande fase-5.1 grens blijft intact:

1. de webapp draait als niet-root `vst-control`;
2. mutaties vereisen de bestaande operatorbinding en CSRF-controle;
3. de webapp schrijft uitsluitend een exclusief queuebestand;
4. de root-executor valideert request-schema, leeftijd, operator, tenant-key, host, naam en moduleallowlist opnieuw;
5. onbekende top-level of provisioningvelden worden geweigerd;
6. tenantroot, PDO-profiel, HTTPS-URL en provisionerscript worden server-side bepaald;
7. de executor controleert opnieuw dat tenant-key en host nog vrij zijn;
8. eerst draait een `--dry-run`; pas daarna mag de bestaande CLI-provisioner muteren;
9. de provisioner draait met dezelfde exact gepinde productie-PHP-binary als de executor;
10. CLI-output met serverpaden wordt niet teruggegeven aan de browser.

De executor blijft shellvrij en gebruikt de bestaande `process521Run()` met argument-arrays en `bypass_shell`.

## Resultaat en status

Na succesvolle basisprovisioning bestaan de normale tenantartifacts onder de server-side tenantroot, waaronder:

- `config.php`;
- `runtime.env`;
- `tenant.json`;
- private opslag;
- neutrale publieke startcontent.

Nieuwe VPS-tenants worden met `--driver=pdo` voorbereid. Zolang nog geen fase-4.8 lifecycle-plan bestaat, neemt de root-generated control-plane snapshot de tenant op met status:

`setup_required` → **Installatie afronden**

Ook een canonieke tenantdirectory met onvolledige of ongeldige provisioningmetadata wordt niet meer stil genegeerd, maar als **Controle nodig** (`invalid`) zichtbaar gemaakt.

## Waarom het eerste beheerderswachtwoord niet in deze wizard staat

De bestaande productiebootstrap heeft bewust een andere securitygrens voor credentials: het eerste tenantbeheerwachtwoord wordt via `bootstrap-tenant-admin.php` uit een interactieve of STDIN-secretstroom gelezen. Wachtwoorden horen niet in argv, environment, Git, logs of de control-plane queue.

Fase 5.7 verlaagt die lat niet. Na basisprovisioning moet de eerste beheerder daarom nog via de veilige server-side bootstrap worden geactiveerd.

Daarna moeten voor deze beperkte eerste variant de bestaande VPS-infrastructuurstappen nog worden afgerond: deployment/runtime, PostgreSQL, Apache-vhost, DNS-readiness, TLS, monitoring en lifecycle-adoptie. De tenantkaart vermeldt dit expliciet.

Een toekomstige volledig self-service onboarding mag deze resterende stappen pas vanuit de GUI koppelen als ook voor de eerste beheercredential een aparte secretveilige overdracht bestaat. Het opslaan van een plaintext wachtwoord in de queue is nadrukkelijk geen toegestane oplossing.

## Acceptatie

`tests/phase57-control-plane-provisioning.php` controleert onder meer:

- canonieke key/host/modulevalidatie;
- weigering van dubbele keys en hosts in de weblaag;
- absence van secrets, commando's, argv en serverpaden in requests;
- executor-side hervalidatie en veldsmuggling-blokkade;
- server-side PDO/root/modulebinding;
- verplichte dry-run vóór mutatie;
- productie-PHP-binding;
- snapshotstatus `setup_required`;
- de nieuwe Platformbeheer-UI;
- dat de GUI geen wachtwoordveld voor deze queue introduceert.

Voor live acceptatie op de VPS hoort na merge een wegwerptenant via **Nieuwe vereniging** te worden aangemaakt. Controleer daarna het executorresultaat, de tenantmap, de status **Installatie afronden** en de auditlog voordat de tenant weer gecontroleerd wordt opgeruimd.
