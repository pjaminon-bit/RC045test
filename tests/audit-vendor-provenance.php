<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;

function avpCheck(bool $cond, string $label): void {
    global $ok, $fout;
    if ($cond) { $ok++; echo "OK: {$label}\n"; }
    else { $fout++; fwrite(STDERR, "FOUT: {$label}\n"); }
}

function avpGitBlobSha(string $pad): string {
    $inhoud = @file_get_contents($pad);
    if (!is_string($inhoud)) {
        throw new RuntimeException('Vendorbestand ontbreekt: ' . $pad);
    }
    return sha1('blob ' . strlen($inhoud) . "\0" . $inhoud);
}

$vendor = $root . '/vendor/photoswipe';
$verwacht = [
    'LICENSE' => '5e0ff4d6c825895d919e888b6985caef745bbb74',
    'photoswipe-lightbox.esm.min.js' => 'cac7e4e0f8b8bed99b14273c544652f5c208808e',
    'photoswipe.css' => '686dfc36a68aa72bb5bd94da49b391b76a29ba9b',
    'photoswipe.esm.min.js' => 'cc924b79afa73872c466467d64da07bfe0d0953d',
];

foreach ($verwacht as $bestand => $sha) {
    avpCheck(
        hash_equals($sha, avpGitBlobSha($vendor . '/' . $bestand)),
        "PhotoSwipe {$bestand} is byte-identiek aan upstream v5.4.4"
    );
}

$provenance = @file_get_contents($vendor . '/PROVENANCE.md');
avpCheck(is_string($provenance), 'PhotoSwipe provenancebestand bestaat');
if (is_string($provenance)) {
    avpCheck(str_contains($provenance, 'v5.4.4'), 'provenance legt upstream tag vast');
    avpCheck(
        str_contains($provenance, 'fd85184b450f451bc4aa2697f6d0a79304d13473'),
        'provenance legt upstream tagcommit vast'
    );
    foreach ($verwacht as $sha) {
        avpCheck(str_contains($provenance, $sha), 'provenance bevat vendored Git-blob ' . $sha);
    }
}

$core = @file_get_contents($vendor . '/photoswipe.esm.min.js');
avpCheck(
    is_string($core) && str_contains($core, 'PhotoSwipe 5.4.4'),
    'vendored core identificeert zichzelf als PhotoSwipe 5.4.4'
);

echo "Audit vendor provenance: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
