<?php
// Fase 5.2.1 — pure security helpers voor productie-hardening.
// Geen rootmutaties in dit bestand.

function security521ReleaseBinding(
    string $commit,
    string $expectedPath,
    string $manifestSha256,
    string $currentReal,
    array $marker,
    array $state
): void {
    if (preg_match('/^[0-9a-f]{40}$/D', $commit) !== 1
        || preg_match('/^[0-9a-f]{64}$/D', $manifestSha256) !== 1) {
        throw new RuntimeException('Ongeldige verwachte releasebinding.');
    }
    $expectedPath = runtime41NormPad($expectedPath);
    $currentReal = runtime41NormPad($currentReal);
    if (!hash_equals($expectedPath, $currentReal)) {
        throw new RuntimeException('current wijst niet naar de fase-5.2 releasecommit.');
    }
    if ((int)($marker['schema'] ?? 0) !== 1
        || ($marker['phase'] ?? '') !== '4.7-release'
        || ($marker['immutable'] ?? false) !== true
        || !hash_equals($commit, (string)($marker['commit'] ?? ''))
        || !hash_equals($manifestSha256, (string)($marker['manifest_sha256'] ?? ''))) {
        throw new RuntimeException('Release marker wijkt af van het fase-5.2 plan.');
    }
    if ((int)($state['schema'] ?? 0) !== 1
        || ($state['phase'] ?? '') !== '4.7-state'
        || !is_array($state['active'] ?? null)
        || ($state['transition'] ?? null) !== null) {
        throw new RuntimeException('Release-state is niet stabiel voor fase-5.2 bootstrap.');
    }
    $active = $state['active'];
    if (!hash_equals($commit, (string)($active['commit'] ?? ''))
        || !hash_equals($expectedPath, runtime41NormPad((string)($active['path'] ?? '')))
        || !hash_equals($manifestSha256, (string)($active['manifest_sha256'] ?? ''))) {
        throw new RuntimeException('Actieve release-state wijkt af van het fase-5.2 plan.');
    }
}

function security521GitSourceBinding(
    string $expectedCommit,
    string $expectedRoot,
    string $gitTopLevel,
    string $gitHead,
    string $gitStatus
): void {
    $expectedCommit = strtolower(trim($expectedCommit));
    $gitHead = strtolower(trim($gitHead));
    if (preg_match('/^[0-9a-f]{40}$/D', $expectedCommit) !== 1
        || preg_match('/^[0-9a-f]{40}$/D', $gitHead) !== 1) {
        throw new RuntimeException('Git source-binding bevat geen exacte 40-teken commit.');
    }
    $expectedRoot = runtime41NormPad($expectedRoot);
    $gitTopLevel = runtime41NormPad(trim($gitTopLevel));
    if (!runtime41IsAbsoluutPad($expectedRoot) || !runtime41IsAbsoluutPad($gitTopLevel)
        || !hash_equals($expectedRoot, $gitTopLevel)) {
        throw new RuntimeException('Git top-level wijkt af van de geplande releasebron.');
    }
    if (!hash_equals($expectedCommit, $gitHead)) {
        throw new RuntimeException('Git HEAD wijkt af van de geplande releasecommit.');
    }
    if (trim($gitStatus) !== '') {
        throw new RuntimeException('Git releasebron bevat tracked of untracked wijzigingen.');
    }
}
