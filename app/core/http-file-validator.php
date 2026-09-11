<?php
// ============================================================
// O(1) HTTP-validator voor atomisch beheerde publieke bestanden
// ============================================================
// De validator is bewust versie-/inodegebonden en leest geen bestandbytes.
// Ondersteunde writers vervangen bestaande publieke assets atomisch vanuit een
// sibling-tempfile. Daardoor krijgt iedere geactiveerde versie een nieuw inode,
// ook wanneer bytegrootte en mtime gelijk blijven. Dezelfde geopende handle
// levert zowel validator als responsebody, zodat path replacement tijdens een
// request geen validator/body-mismatch kan veroorzaken.

function httpFileValidatorVoorHandle($handle): ?array
{
    if (!is_resource($handle)) return null;
    $stat = @fstat($handle);
    if (!is_array($stat)) return null;

    foreach (['dev', 'ino', 'mode', 'size', 'mtime', 'ctime'] as $sleutel) {
        if (!isset($stat[$sleutel]) || !is_int($stat[$sleutel])) return null;
    }
    if (($stat['mode'] & 0170000) !== 0100000 || $stat['size'] < 0) return null;

    $identiteit = implode(':', [
        (string) $stat['dev'],
        (string) $stat['ino'],
        (string) $stat['size'],
        (string) $stat['mtime'],
        (string) $stat['ctime'],
    ]);

    return [
        'size' => $stat['size'],
        'etag' => '"asset-v1-' . hash('sha256', $identiteit) . '"',
        'stat' => $stat,
    ];
}

function httpFileOpenMetValidator(string $pad): ?array
{
    $handle = @fopen($pad, 'rb');
    if ($handle === false) return null;
    $validator = httpFileValidatorVoorHandle($handle);
    if ($validator === null) {
        fclose($handle);
        return null;
    }
    return ['handle' => $handle] + $validator;
}
