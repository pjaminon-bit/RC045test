<?php
// Run from repository root: php tests/phase25-architecture.php
$root = dirname(__DIR__);
$errors = [];
$ok = [];
function t25($cond, $message) { global $errors,$ok; if($cond)$ok[]=$message;else$errors[]=$message; }
function t25file($path) { return is_file($path) ? (string)file_get_contents($path) : ''; }

$platform = require $root . '/app/core/platform-definities.php';
$features = $platform['features'] ?? [];
$beheer = $platform['beheer'] ?? [];
$caps = $platform['capabilities'] ?? [];
$config = require $root . '/site-config.php';

t25(is_array($features) && $features, 'platform features aanwezig');
t25(is_array($beheer) && $beheer, 'beheerregistry aanwezig');
t25(is_array($caps) && $caps, 'capabilityregistry aanwezig');

$featureAdapter = require $root . '/app/core/module-definities.php';
$beheerAdapter = require $root . '/app/beheer/module-registry.php';
t25($featureAdapter === $features, 'module-definities is zuivere platformadapter');
t25(count($beheerAdapter) === count($beheer), 'beheerregistry heeft exact alle platformcomponenten');

$routes = [];
foreach ($beheer as $key=>$def) {
    t25(is_array($def), "beheercomponent $key is array");
    $cap = (string)($def['capability'] ?? '');
    t25($cap !== '' && isset($caps[$cap]), "beheercomponent $key verwijst naar geldige capability");
    $route = (string)($def['route'] ?? '');
    $path = (string)parse_url($route, PHP_URL_PATH);
    t25($path !== '' && is_file($root . '/beheer/' . ltrim($path,'/')), "beheerroute $key bestaat");
    if ($route !== '') { t25(!isset($routes[$route]), "beheerroute $route is uniek"); $routes[$route]=$key; }
    $feature = trim((string)($def['feature'] ?? ''));
    if ($feature !== '') {
        t25(isset($features[$feature]), "feature $feature voor $key bestaat");
        t25(array_key_exists($feature, (array)($config['modules'] ?? [])), "feature $feature is tenant-configureerbaar");
    }
}

$verplicht = [
    'leden'=>'members.view','leden_import'=>'members.manage','commissies'=>'committees.manage',
    'vergaderingen'=>'meetings.manage','taken'=>'tasks.manage','operationele_taken'=>'ops_tasks.manage',
    'evenementen'=>'events.manage','aanmeldingen'=>'applications.manage','lidmaatschapstypen'=>'memberships.fees.manage',
];
foreach($verplicht as $component=>$cap)t25(($beheer[$component]['capability']??null)===$cap,"fase-2.5 component $component geregistreerd");
t25(!empty($caps['members.erase']['gevoelig']), 'definitief leden wissen is gevoelige capability');
t25(!empty($caps['system.users.manage']['gevoelig']), 'gebruikersbeheer is gevoelige capability');
t25(!empty($caps['system.audit.read']['gevoelig']), 'auditlog is gevoelige capability');
t25(!empty($caps['system.backups.manage']['gevoelig']), 'backupherstel is gevoelige capability');

$beheerIndex = t25file($root . '/beheer/index.php');
t25(strlen($beheerIndex) < 50000, 'beheer/index.php is een dunne shell');
t25(strpos($beheerIndex, 'formulier ===') === false && strpos($beheerIndex, "formulier'] ===") === false, 'beheer-shell bevat geen inhoudelijke legacy POST-handlers');
t25(strpos($beheerIndex, 'authPlatformDefinities') !== false, 'beheer-shell gebruikt centraal platformregister');

$ledenIndex = t25file($root . '/leden/index.php');
t25(strpos($ledenIndex, 'leden-app.php') === false, '/leden/ is geen wrapper meer om legacy leden-app');
t25(strpos($ledenIndex, 'Mijn ') !== false && strpos($ledenIndex, 'portaal-service.php') !== false, '/leden/ is persoonlijk portaal');

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$verkeerdeHelper = [];
$zoekterm = 'site' . 'VerenigingNaam(';
foreach($it as $file){
    if(!$file->isFile()||strtolower($file->getExtension())!=='php')continue;
    $pad=$file->getPathname();
    if(realpath($pad)===realpath(__FILE__))continue;
    if(strpos($pad,DIRECTORY_SEPARATOR.'.git'.DIRECTORY_SEPARATOR)!==false)continue;
    $txt=(string)file_get_contents($pad);
    if(strpos($txt,$zoekterm)!==false)$verkeerdeHelper[]=$pad;
}
t25(!$verkeerdeHelper, 'geen niet-bestaande sitenaam-helper gebruikt');

$muterend = [
    'beheer/leden.php'=>'members.', 'beheer/leden-import.php'=>'members.manage',
    'beheer/commissies.php'=>'committees.manage','beheer/vergaderingen.php'=>'meetings.manage',
    'beheer/taken.php'=>'tasks.manage','beheer/operationele-taken.php'=>'ops_tasks.manage',
    'beheer/evenementen.php'=>'events.manage','beheer/aanmeldingen.php'=>'applications.manage',
    'beheer/lidmaatschap.php'=>'memberships.fees.manage','beheer/gebruikers.php'=>'system.users.manage',
    'beheer/backups.php'=>'system.backups.manage',
];
foreach($muterend as $rel=>$needle){$txt=t25file($root.'/'.$rel);t25($txt!=='',"$rel bestaat");t25(strpos($txt,'csrfOk(')!==false,"$rel controleert CSRF");t25(strpos($txt,$needle)!==false,"$rel controleert capability $needle");}

$ontvangst = t25file($root . '/aanmelden-ontvangst.php');
t25(strpos($ontvangst,'aanmeldingenSchrijf')!==false, 'openbare aanmelding schrijft naar inbox');
t25(strpos($ontvangst,"['leden'][]")===false && strpos($ontvangst,'ledenSchrijf(')===false, 'openbare aanmelding maakt niet rechtstreeks een lid');
t25(strpos($ontvangst,'lidmaatschapBedragVoorMaand')!==false, 'openbare aanmelding berekent bedrag server-side');

$service = t25file($root . '/app/leden/service.php');
t25(strpos($service,"empty(\$gevonden['gearchiveerd_op'])")!==false, 'definitief wissen vereist eerst archiveren');
t25(strpos($service,'ledenServiceVerwijderRelaties')!==false, 'definitief wissen ruimt bekende relaties op');

$ht = t25file($root . '/.htaccess');
foreach(['leden-data\\.php','aanmeldingen-data\\.php','vergaderingen-data\\.php','taken-data\\.php','operationele-taken-data\\.php','evenementen-data\\.php'] as $needle)t25(strpos($ht,$needle)!==false,".htaccess beschermt $needle");
$backup = require $root . '/beheer/backup-registry.php';
foreach(['leden','aanmeldingen_inbox','vergaderingen','taken','operationele_taken','evenementen','lidmaatschapstypen'] as $key)t25(isset($backup[$key]),"backupregistry bevat $key");

require_once $root . '/app/leden/lidmaatschap.php';
$types = lidmaatschapLees()['types'] ?? [];
$ids = array_map(static fn($t)=>(string)($t['id']??''),$types);
t25(count($ids) === count(array_unique($ids)), 'lidmaatschapstype-id’s zijn uniek');
t25(count($types) >= 1, 'minstens één lidmaatschapstype beschikbaar');
foreach($types as $type){$min=$type['leeftijd_min'];$max=$type['leeftijd_max'];t25($min===null||$max===null||$min<=$max,'leeftijdsgrens lidmaatschapstype is geldig');t25(($type['jaarbedrag']??-1)>=0,'jaarbedrag is niet negatief');}

t25(trim((string)($config['vereniging']['sleutel']??''))!=='','tenant heeft vaste technische sleutel');
t25(in_array((string)($config['opslag']['private_driver']??''),['json','pdo'],true),'private storage driver is geldig');

require_once $root . '/app/auth-capabilities.php';
$ledenCaps=authCapabilitiesVanTabs(['leden']);
foreach(['members.view','members.manage','members.fees.manage'] as $c)t25(in_array($c,$ledenCaps,true),"legacy ledenrecht behoudt $c");

echo "Phase 2.5 checks: " . count($ok) . " OK, " . count($errors) . " fout(en)\n";
if($errors){foreach($errors as $e)fwrite(STDERR,"FOUT: $e\n");exit(1);}foreach($ok as $m)echo "OK: $m\n";
