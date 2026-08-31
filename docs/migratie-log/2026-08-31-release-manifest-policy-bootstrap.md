# Release-manifestpolicy bootstrap — 31 augustus 2026

## Aanleiding

Na het uitsluiten van `ops/` uit de immutable applicatierelease bleef de bestaande actieve release gemarkeerd met de eerdere manifestregels waarin `ops/` nog meetelde. Daardoor valideerde de actuele release-inhoud correct onder de legacy-regels, maar keurde de nieuwere release-tooling dezelfde marker af omdat zij een andere manifestselectie gebruikte.

Dit was geen inhoudelijke drift of tampering. Zowel de actieve release `5cafa580d369fded52efc4af901b2f5d9c6fe2d6` als de vorige release `d9c99b42c66fed5c77d45152778f37162a24dbc5` zijn vóór herstel opnieuw volledig tegen de legacy manifestregels gevalideerd.

## Structurele oplossing

PR #128 introduceert expliciete manifestpolicy-versies:

- policy 1: legacy selectie waarin `ops/` onderdeel is van het release-manifest;
- policy 2: actuele selectie waarin `ops/` buiten de applicatierelease blijft;
- markers zonder policy worden uitsluitend als policy 1 geïnterpreteerd;
- nieuwe plannen en markers leggen policy 2 expliciet vast;
- onbekende policies worden fail-closed geweigerd;
- hash-, file-count- en byte-validatie blijven verplicht.

## Bootstrapstrategie

De eerste compatibele release wordt éénmalig geactiveerd via de reeds gevalideerde legacy release-tooling uit `d9c99b42c66fed5c77d45152778f37162a24dbc5`. Er worden daarbij geen bestaande marker- of statebestanden handmatig herschreven.

De daaropvolgende normale GitHub-deploy gebruikt de nieuwe actieve tooling en produceert de eerste expliciete policy-2 release. Daarmee is de reguliere immutable releaseketen weer volledig zelfdragend.

## Beveiligingsuitgangspunten

- geen `chmod 777` of verruiming van releasepermissies;
- geen uitschakeling van immutable manifestcontrole;
- geen handmatige wijziging van bestaande release-inhoud;
- geen herschrijving van bestaande markers om een controle te omzeilen;
- bootstrap alleen nadat legacy byte-integriteit van actieve en vorige release is bewezen;
- volgende release verloopt weer via de normale CI/deployketen.
