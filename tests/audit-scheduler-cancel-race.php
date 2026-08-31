<?php
$root = dirname(__DIR__);
$runner = @file_get_contents($root . '/bin/control-plane-scheduled-run.php');
$executor = @file_get_contents($root . '/bin/control-plane-executor.php');
$checks = [
    'runner lock' => is_string($runner) && str_contains($runner, 'function cpsScheduleLock'),
    'runner exclusive flock' => is_string($runner) && str_contains($runner, 'flock($h,LOCK_EX)'),
    'runner keeps lock through queued write' => is_string($runner) && str_contains($runner, "\$doc['status']='queued'") && str_contains($runner, 'finally{flock($lock,LOCK_UN);fclose($lock);}'),
    'executor same lock suffix' => is_string($executor) && str_contains($executor, "\$path=\$dir.'/'.$id.'.lock'"),
    'safe cancel route' => is_string($executor) && str_contains($executor, 'cpeScheduleCancelVeilig'),
    'systemctl result checked' => is_string($executor) && str_contains($executor, 'if($code!==0)throw new RuntimeException(\'Geplande systemd-unit kon niet veilig worden gestopt:'),
];
$ok=0;
foreach($checks as$label=>$passed){if($passed){echo"OK: {$label}\n";$ok++;}else fwrite(STDERR,"FOUT: {$label}\n");}
echo 'Scheduler cancel regression: '.$ok.'/'.count($checks)." OK\n";
exit($ok===count($checks)?0:1);
