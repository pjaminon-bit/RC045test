<?php
$id=trim((string)($_GET['id']??''));
$doel='leden.php';
if($id!=='')$doel.='?edit='.rawurlencode($id).'#groepen';
http_response_code(308);
header('Location: '.$doel);
exit;
