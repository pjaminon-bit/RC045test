<?php
$groepType='werkgroep';
$groepCapability='workgroups.manage';
$groepTitel='Werkgroepen';
ob_start(static function($html){if(!is_string($html)||stripos($html,'</head>')===false)return$html;return preg_replace('~</head>~i','<link rel="stylesheet" href="ui-2026.css"></head>',$html,1)??$html;});
require dirname(__DIR__).'/app/beheer/groepen-beheer.php';
