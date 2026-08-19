<?php
// ============================================================
// Modulaire beheerpagina: Agenda
// ============================================================

require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/site.php';
require_once dirname(__DIR__) . '/app/data-slot.php';

if (!$ingelogd) { header('Location: ../beheer.php'); exit; }
// De Agenda-beheertab hoort in module-definities bij de feature
// `evenementen`. Gebruik daarom dezelfde feature flag als beheer.php; een
// aparte feature `agenda` bestaat niet in de centrale moduledefinities.
if (!siteModuleActief('evenementen')) { http_response_code(404); echo 'De agendamodule is voor deze vereniging niet ingeschakeld.'; exit; }
$rechten = authRechten(['agenda' => 'Agenda'], []);
if (!$isMaster && !in_array('agenda', $rechten['toegestaneTabs'] ?? [], true)) { http_response_code(403); echo 'Geen toegang tot Agenda.'; exit; }

require __DIR__ . '/agenda-view.php';
