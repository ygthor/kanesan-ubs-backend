<?php

declare(strict_types=1);

/**
 * Database backup script for cPanel cron.
 *
 * Example cron command:
 *   php /home/USER/path/to/backend/backupdb.php
 */

use Illuminate\Contracts\Console\Kernel;

const EXIT_OK = 0;
const EXIT_FAIL = 1;

function println(string $message): void
{
    echo '[' . date('Y-m-d H:i:s') . "] {$message}\n";
}

function fail(string $message, int $code = EXIT_FAIL): never
{
    println('ERROR: ' . $message);
    exit($code);
}

$basePath = __DIR__;
$autoloadPath = $basePath . '/vendor/autoload.php';
$bootstrapPath = $basePath . '/bootstrap/app.php';

if (!is_file($autoloadPath) || !is_file($bootstrapPath)) {
    fail('This script must run from Laravel project root (backend).');
}

require_once $autoloadPath;
$app = require_once $bootstrapPath;
$app->make(Kernel::class)->bootstrap();

$backupDir = $basePath . '/storage/app/db-backups';
$lockFile = $backupDir . '/backupdb.lock';
$retentionDays = (int) env('DB_BACKUP_RETENTION_DAYS', 14);

if (!is_dir($backupDir) && !mkdir($backupDir, 0755, true) && !is_dir($backupDir)) {
    fail("Unable to create backup directory: {$backupDir}");
}

$lockHandle = fopen($lockFile, 'c+');
if ($lockHandle === false) {
    fail("Unable to create/open lock file: {$lockFile}");
}

if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
    fclose($lockHandle);
    fail('Another backup process is already running.');
}

$connectionName = (string) config('database.default');
$connection = (array) config("database.connections.{$connectionName}");

if (empty($connection)) {
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
    fail("Database connection config not found: {$connectionName}");
}

$driver = (string) ($connection['driver'] ?? '');
$now = date('Ymd_His');
$prefix = 'db_backup_' . $connectionName . '_' . $now;

try {
    if ($driver === 'mysql' || $driver === 'mariadb') {
        $host = (string) ($connection['host'] ?? '127.0.0.1');
        $port = (string) ($connection['port'] ?? '3306');
        $database = (string) ($connection['database'] ?? '');
        $username = (string) ($connection['username'] ?? '');
        $password = (string) ($connection['password'] ?? '');

        if ($database === '' || $username === '') {
            throw new RuntimeException('Missing DB_DATABASE or DB_USERNAME.');
        }

        $dumpFile = "{$backupDir}/{$prefix}.sql";
        $binaries = ['mysqldump', 'mariadb-dump'];
        $dumpBinary = null;

        foreach ($binaries as $bin) {
            $check = trim((string) shell_exec("command -v {$bin} 2>/dev/null"));
            if ($check !== '') {
                $dumpBinary = $check;
                break;
            }
        }

        if ($dumpBinary === null) {
            throw new RuntimeException('mysqldump/mariadb-dump not found on server PATH.');
        }

        $cmd = implode(' ', [
            escapeshellarg($dumpBinary),
            '--single-transaction',
            '--quick',
            '--routines',
            '--triggers',
            '--events',
            '-h', escapeshellarg($host),
            '-P', escapeshellarg($port),
            '-u', escapeshellarg($username),
            '--password=' . escapeshellarg($password),
            escapeshellarg($database),
            '>',
            escapeshellarg($dumpFile),
            '2>',
            escapeshellarg("{$dumpFile}.err"),
        ]);

        exec($cmd, $output, $exitCode);
        if ($exitCode !== 0 || !is_file($dumpFile) || filesize($dumpFile) === 0) {
            $errMsg = is_file("{$dumpFile}.err")
                ? trim((string) file_get_contents("{$dumpFile}.err"))
                : 'Unknown dump error';
            throw new RuntimeException("Dump failed (exit {$exitCode}): {$errMsg}");
        }

        @unlink("{$dumpFile}.err");
        println("Backup created: {$dumpFile}");
    } elseif ($driver === 'sqlite') {
        $sqlitePath = (string) ($connection['database'] ?? '');
        if ($sqlitePath === '' || !is_file($sqlitePath)) {
            throw new RuntimeException("SQLite database file not found: {$sqlitePath}");
        }

        $backupFile = "{$backupDir}/{$prefix}.sqlite";
        if (!copy($sqlitePath, $backupFile)) {
            throw new RuntimeException('Unable to copy SQLite database file.');
        }
        println("SQLite backup created: {$backupFile}");
    } else {
        throw new RuntimeException("Unsupported DB driver: {$driver}");
    }

    // Delete backups older than retention days.
    if ($retentionDays > 0) {
        $threshold = time() - ($retentionDays * 86400);
        foreach ((array) glob($backupDir . '/db_backup_*') as $file) {
            if (is_file($file) && filemtime($file) !== false && filemtime($file) < $threshold) {
                @unlink($file);
            }
        }
    }

    println('Backup job completed.');
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
    exit(EXIT_OK);
} catch (Throwable $e) {
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
    fail($e->getMessage());
}
