from pathlib import Path

p = Path(__file__).resolve().parents[1] / 'tests/phase321-runtime-failclosed.php'
s = p.read_text()
old = "    file_put_contents($tenantCfg,\"<?php return ['vereniging'=>['sleutel'=>'tenant-veilig','naam'=>'Tenant Veilig']];\\n\");"
new = "    file_put_contents($tenantCfg,\"<?php return ['vereniging'=>['sleutel'=>'tenant-veilig','naam'=>'Tenant Veilig'],'opslag'=>['private_driver'=>'json']];\\n\");"
if old not in s:
    raise SystemExit('phase321 valid external tenant fixture not found')
p.write_text(s.replace(old, new, 1))
