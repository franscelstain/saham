<?php

/**
 * tools/contract_check.php
 *
 * STRICT contract checker for docs/watchlist/preopen.md (LOCKED).
 * Purpose:
 * - Run in CI to guarantee "no extra keys" output contract stays stable.
 *
 * Usage:
 *   php tools/contract_check.php
 *   php tools/contract_check.php tests/Fixtures/watchlist/preopen_weekly_swing.json
 *
 * Notes:
 * - By default, this tool validates ONLY fixtures that match the locked preopen contract,
 *   i.e. files named "preopen_*.json" under tests/Fixtures/watchlist.
 * - Legacy fixtures (older schemas) may still exist in the folder; they are intentionally
 *   excluded unless you pass explicit file paths.
 */

$root = dirname(__DIR__);

$autoload = $root . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require $autoload;
} else {
    // No vendor in this repo export → load validator directly (keeps zip small).
    require $root . '/app/Trade/Watchlist/Contracts/PreopenContractValidator.php';
}

use App\Trade\Watchlist\Contracts\PreopenContractValidator;

$validator = new PreopenContractValidator();

$files = [];
if ($argc > 1) {
    for ($i = 1; $i < $argc; $i++) $files[] = $argv[$i];
} else {
    $glob = glob($root . '/tests/Fixtures/watchlist/preopen_*.json') ?: [];
    $files = array_values($glob);
}

if (empty($files)) {
    fwrite(STDERR, "No fixture files found.\n");
    exit(2);
}

$ok = 0;
$fail = 0;

foreach ($files as $fp) {
    $name = basename($fp);
    $raw = @file_get_contents($fp);
    if ($raw === false) {
        $fail++;
        fwrite(STDERR, "[FAIL] {$name}: cannot read\n");
        continue;
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $fail++;
        fwrite(STDERR, "[FAIL] {$name}: invalid JSON\n");
        continue;
    }

    try {
        $validator->validate($data);
        $ok++;
        fwrite(STDOUT, "[OK] {$name}\n");
    } catch (\Throwable $e) {
        $fail++;
        fwrite(STDERR, "[FAIL] {$name}: " . $e->getMessage() . "\n");
    }
}

if ($fail > 0) {
    fwrite(STDERR, "Contract check failed: ok={$ok} fail={$fail}\n");
    exit(1);
}

fwrite(STDOUT, "Contract check passed: ok={$ok}\n");
exit(0);
