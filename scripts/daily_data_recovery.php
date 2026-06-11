<?php

declare(strict_types=1);

/**
 * Daily data recovery fixes for synced/order data.
 *
 * Intended for cron:
 *   php /path/to/backend/scripts/daily_data_recovery.php
 *
 * Useful options:
 *   --dry-run              Show planned updates without writing.
 *   --fix=all              Run all fixes. Options: all, cn-links, agent-no.
 *   --tolerance=120        Max accepted item-created-time difference in seconds for CN links.
 *   --order-tolerance=5    Max accepted order_date difference in seconds for CN links.
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

const EXIT_OK = 0;
const EXIT_FAIL = 1;

function println(string $message): void
{
    echo '[' . date('Y-m-d H:i:s') . "] {$message}\n";
}

function failScript(string $message, int $code = EXIT_FAIL): never
{
    println('ERROR: ' . $message);
    exit($code);
}

function argValue(array $argv, string $name, ?string $default = null): ?string
{
    $prefix = "--{$name}=";
    foreach ($argv as $arg) {
        if (str_starts_with($arg, $prefix)) {
            return substr($arg, strlen($prefix));
        }
    }

    return $default;
}

function recoveryWriteFixLog(string $prefix, array $entries, array $stats): ?string
{
    if ($entries === []) {
        return null;
    }

    $logDir = __DIR__ . '/recovery_fixed/logs';
    if (!is_dir($logDir) && !mkdir($logDir, 0755, true) && !is_dir($logDir)) {
        throw new RuntimeException("Unable to create recovery log directory: {$logDir}");
    }

    $logFile = $logDir . '/' . $prefix . '_' . date('Ymd_His') . '.log';
    $lines = [
        'created_at=' . date('Y-m-d H:i:s'),
        'fix=' . $prefix,
        'stats=' . json_encode($stats, JSON_UNESCAPED_SLASHES),
        'entries:',
    ];

    foreach ($entries as $entry) {
        $lines[] = json_encode($entry, JSON_UNESCAPED_SLASHES);
    }

    $bytes = file_put_contents($logFile, implode(PHP_EOL, $lines) . PHP_EOL, LOCK_EX);
    if ($bytes === false) {
        throw new RuntimeException("Unable to write recovery log: {$logFile}");
    }

    return $logFile;
}

$basePath = dirname(__DIR__);
$autoloadPath = $basePath . '/vendor/autoload.php';
$bootstrapPath = $basePath . '/bootstrap/app.php';

if (!is_file($autoloadPath) || !is_file($bootstrapPath)) {
    failScript('This script must run inside the Laravel backend project.');
}

require_once $autoloadPath;
$app = require_once $bootstrapPath;
$app->make(Kernel::class)->bootstrap();

require_once __DIR__ . '/recovery_fixes/cn_links.php';
require_once __DIR__ . '/recovery_fixes/agent_no.php';

$dryRun = in_array('--dry-run', $argv, true);
$fix = argValue($argv, 'fix', 'all');
$validFixes = ['all', 'cn-links', 'agent-no'];
$itemToleranceSeconds = (int) argValue($argv, 'tolerance', (string) env('CN_LINK_FIX_TOLERANCE_SECONDS', 120));
$orderToleranceSeconds = (int) argValue($argv, 'order-tolerance', (string) env('CN_LINK_FIX_ORDER_TOLERANCE_SECONDS', 5));

if (!in_array($fix, $validFixes, true)) {
    failScript('--fix must be one of: ' . implode(', ', $validFixes));
}
if ($itemToleranceSeconds < 0) {
    failScript('--tolerance must be zero or greater.');
}
if ($orderToleranceSeconds < 0) {
    failScript('--order-tolerance must be zero or greater.');
}

$lockFile = storage_path('app/daily_data_recovery.lock');
$lockHandle = fopen($lockFile, 'c+');

if ($lockHandle === false) {
    failScript("Unable to create/open lock file: {$lockFile}");
}

if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
    fclose($lockHandle);
    failScript('Another daily_data_recovery process is already running.');
}

try {
    println('Starting daily data recovery' . ($dryRun ? ' (dry-run)' : ''));
    println("Selected fix: {$fix}");

    DB::beginTransaction();

    $summary = [];

    if ($fix === 'all' || $fix === 'cn-links') {
        $summary['cn_links'] = recoveryFixMissingCnCreditLinks($dryRun, $itemToleranceSeconds, $orderToleranceSeconds);
    }

    if ($fix === 'all' || $fix === 'agent-no') {
        $summary['agent_no'] = recoveryFixMissingOrderAgentNo($dryRun);
    }

    if ($dryRun) {
        DB::rollBack();
    } else {
        DB::commit();
    }

    if (!$dryRun) {
        $logMap = [
            'cn_links' => 'cn_link_fix',
            'agent_no' => 'agent_no_fix',
        ];

        foreach ($summary as $name => $stats) {
            $entries = $stats['log_entries'] ?? [];
            if ($entries === []) {
                continue;
            }

            $statsForLog = $stats;
            unset($statsForLog['log_entries']);

            $logFile = recoveryWriteFixLog($logMap[$name] ?? $name, $entries, $statsForLog);
            if ($logFile !== null) {
                println("Log {$name}: {$logFile}");
            }
        }
    }

    foreach ($summary as $name => $stats) {
        unset($stats['log_entries']);
        println("Summary {$name}: " . json_encode($stats));
    }

    println('Daily data recovery completed.');
} catch (Throwable $e) {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
    failScript($e->getMessage());
} finally {
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
}

exit(EXIT_OK);
