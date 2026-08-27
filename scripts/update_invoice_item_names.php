<?php

declare(strict_types=1);

/**
 * Server script to update missing product_name and description on remote order_items table from icitem (DESP).
 *
 * Usage (Run on server):
 *   php update_invoice_item_names.php            (this month only by default)
 *   php update_invoice_item_names.php 2026-08-01 (custom start date)
 *   php update_invoice_item_names.php --all      (all records)
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$basePath = dirname(__DIR__);
$autoloadPath = $basePath . '/vendor/autoload.php';
$bootstrapPath = $basePath . '/bootstrap/app.php';

if (!is_file($autoloadPath) || !is_file($bootstrapPath)) {
    echo "❌ Error: This script must run inside the Laravel backend directory.\n";
    exit(1);
}

require_once $autoloadPath;
$app = require_once $bootstrapPath;
$app->make(Kernel::class)->bootstrap();

// Parse CLI arguments
$args = array_slice($argv, 1);
$isAll = in_array('--all', $args, true);
$startDate = date('Y-m-01'); // Default: start of current month (e.g. 2026-08-01)

foreach ($args as $arg) {
    if (str_starts_with($arg, '--since=')) {
        $startDate = substr($arg, 8);
    } elseif (str_starts_with($arg, '--date=')) {
        $startDate = substr($arg, 7);
    } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $arg)) {
        $startDate = $arg;
    }
}

echo "═══════════════════════════════════════════════════════════\n";
echo "  UPDATE INVOICE ITEM NAMES (SERVER SCRIPT)\n";
echo "  " . date('Y-m-d H:i:s') . "\n";
if ($isAll) {
    echo "  Filter: ALL RECORDS (No date filter)\n";
} else {
    echo "  Filter: THIS MONTH ONLY (Order Date >= {$startDate})\n";
}
echo "═══════════════════════════════════════════════════════════\n\n";

try {
    if ($isAll) {
        $affected = DB::update("
            UPDATE order_items oi
            JOIN icitem i ON oi.product_no COLLATE utf8mb4_unicode_ci = i.ITEMNO COLLATE utf8mb4_unicode_ci
            SET 
                oi.product_name = COALESCE(NULLIF(oi.product_name, ''), NULLIF(oi.description, ''), i.DESP),
                oi.description  = COALESCE(NULLIF(oi.description, ''),  NULLIF(oi.product_name, ''), i.DESP)
            WHERE (oi.product_name IS NULL OR oi.product_name = '' OR oi.product_name = 'Unknown' OR oi.description IS NULL OR oi.description = '')
              AND i.DESP IS NOT NULL AND i.DESP != ''
        ");
    } else {
        $affected = DB::update("
            UPDATE order_items oi
            JOIN orders o ON oi.reference_no = o.reference_no
            JOIN icitem i ON oi.product_no COLLATE utf8mb4_unicode_ci = i.ITEMNO COLLATE utf8mb4_unicode_ci
            SET 
                oi.product_name = COALESCE(NULLIF(oi.product_name, ''), NULLIF(oi.description, ''), i.DESP),
                oi.description  = COALESCE(NULLIF(oi.description, ''),  NULLIF(oi.product_name, ''), i.DESP)
            WHERE o.order_date >= ?
              AND (oi.product_name IS NULL OR oi.product_name = '' OR oi.product_name = 'Unknown' OR oi.description IS NULL OR oi.description = '')
              AND i.DESP IS NOT NULL AND i.DESP != ''
        ", [$startDate]);
    }

    echo "✅ Updated {$affected} order_item record(s) successfully.\n\n";

    // Verification check
    if ($isAll) {
        $stats = DB::selectOne("
            SELECT 
                COUNT(*) as total_items,
                SUM(CASE WHEN product_name IS NULL OR product_name = '' OR product_name = 'Unknown' THEN 1 ELSE 0 END) as missing_name,
                SUM(CASE WHEN description IS NULL OR description = '' THEN 1 ELSE 0 END) as missing_description
            FROM order_items
        ");
    } else {
        $stats = DB::selectOne("
            SELECT 
                COUNT(*) as total_items,
                SUM(CASE WHEN oi.product_name IS NULL OR oi.product_name = '' OR oi.product_name = 'Unknown' THEN 1 ELSE 0 END) as missing_name,
                SUM(CASE WHEN oi.description IS NULL OR oi.description = '' THEN 1 ELSE 0 END) as missing_description
            FROM order_items oi
            JOIN orders o ON oi.reference_no = o.reference_no
            WHERE o.order_date >= ?
        ", [$startDate]);
    }

    echo "📊 Status after update:\n";
    echo "   • Total items checked    : " . ($stats->total_items ?? 0) . "\n";
    echo "   • Missing product_name   : " . ($stats->missing_name ?? 0) . "\n";
    echo "   • Missing description    : " . ($stats->missing_description ?? 0) . "\n\n";

    echo "═══════════════════════════════════════════════════════════\n";
    echo "🎉 Completed successfully!\n";
    echo "═══════════════════════════════════════════════════════════\n";

} catch (\Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
