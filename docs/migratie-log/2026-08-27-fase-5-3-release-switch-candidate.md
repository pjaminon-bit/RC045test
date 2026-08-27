# Fase 5.3 — release-switch acceptatiekandidaat

Deze commit is bewust documentatie-only en wijzigt geen runtimegedrag.

Doel: een tweede, inhoudelijk onderscheidbare immutable release maken waarmee op de eerste echte VPS de fase-5.3 releasewissel en handmatige rollback veilig kunnen worden bewezen.

De huidige gevalideerde live baseline blijft `e258593c4803645f050ccaa5ae5a1d929ec5b98f`. Na CI-validatie van deze commit wordt deze kandidaat éénmalig gedeployed, wordt tenant-health gecontroleerd en wordt vervolgens met de release-engine teruggerold naar de eerder gevalideerde baseline. Tenantconfiguratie, database en uploads blijven buiten de releaseboom en mogen door deze test niet wijzigen.
