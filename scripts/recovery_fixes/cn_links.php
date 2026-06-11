<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

function recoveryFixMissingCnCreditLinks(bool $dryRun, int $itemToleranceSeconds, int $orderToleranceSeconds): array
{
    println('Fix: missing CN credit_invoice_no');
    println("  item tolerance: {$itemToleranceSeconds}s");
    println("  order-date tolerance: {$orderToleranceSeconds}s");

    $candidateRows = DB::select(<<<'SQL'
        WITH item_time AS (
            SELECT reference_no, MIN(created_at) AS first_item_created_at
            FROM order_items
            GROUP BY reference_no
        )
        SELECT
            cn.id AS cn_id,
            cn.reference_no AS cn_ref,
            cn.customer_code,
            cn.order_date AS cn_order_date,
            cni.first_item_created_at AS cn_item_time,
            inv.id AS inv_id,
            inv.reference_no AS inv_ref,
            inv.order_date AS inv_order_date,
            invi.first_item_created_at AS inv_item_time,
            ABS(TIMESTAMPDIFF(SECOND, invi.first_item_created_at, cni.first_item_created_at)) AS item_seconds_diff,
            ABS(TIMESTAMPDIFF(SECOND, inv.order_date, cn.order_date)) AS order_seconds_diff
        FROM orders cn
        JOIN item_time cni ON cni.reference_no = cn.reference_no
        JOIN orders inv
            ON inv.type = 'INV'
           AND inv.customer_code = cn.customer_code
           AND DATE(inv.order_date) = DATE(cn.order_date)
        JOIN item_time invi ON invi.reference_no = inv.reference_no
        WHERE cn.type = 'CN'
          AND cn.credit_invoice_no IS NULL
          AND cn.reference_no NOT LIKE 'CN %'
        ORDER BY
            cn.id ASC,
            item_seconds_diff ASC,
            order_seconds_diff ASC,
            inv.id ASC
    SQL);

    $candidatesByCn = [];
    foreach ($candidateRows as $row) {
        $candidatesByCn[(int) $row->cn_id][] = $row;
    }

    $missingCnRows = DB::select(<<<'SQL'
        SELECT id, reference_no
        FROM orders
        WHERE type = 'CN'
          AND credit_invoice_no IS NULL
          AND reference_no NOT LIKE 'CN %'
        ORDER BY id ASC
    SQL);

    $stats = [
        'planned' => 0,
        'updated' => 0,
        'skipped_no_candidate' => 0,
        'skipped_tolerance' => 0,
        'skipped_tie' => 0,
        'log_entries' => [],
    ];

    foreach ($missingCnRows as $cn) {
        $cnId = (int) $cn->id;
        $candidates = $candidatesByCn[$cnId] ?? [];

        if ($candidates === []) {
            $stats['skipped_no_candidate']++;
            println("  SKIP no candidate: CN {$cn->reference_no} (id {$cnId})");
            continue;
        }

        $best = $candidates[0];
        $bestItemDiff = (int) $best->item_seconds_diff;
        $bestOrderDiff = (int) $best->order_seconds_diff;
        $sameBestItemDiffCount = 0;

        foreach ($candidates as $candidate) {
            if ((int) $candidate->item_seconds_diff === $bestItemDiff) {
                $sameBestItemDiffCount++;
            }
        }

        if ($sameBestItemDiffCount > 1) {
            $stats['skipped_tie']++;
            println("  SKIP tie: CN {$best->cn_ref} has {$sameBestItemDiffCount} candidates at {$bestItemDiff}s");
            continue;
        }

        if ($bestItemDiff > $itemToleranceSeconds && $bestOrderDiff > $orderToleranceSeconds) {
            $stats['skipped_tolerance']++;
            println("  SKIP tolerance: CN {$best->cn_ref} -> {$best->inv_ref} item {$bestItemDiff}s, order {$bestOrderDiff}s");
            continue;
        }

        $stats['planned']++;
        $reason = $bestItemDiff <= $itemToleranceSeconds ? 'item-time' : 'order-date';
        println("  FIX CN {$best->cn_ref} -> INV {$best->inv_ref} ({$reason}; item {$bestItemDiff}s, order {$bestOrderDiff}s)");

        if (!$dryRun) {
            $affected = DB::table('orders')
                ->where('id', $cnId)
                ->where('type', 'CN')
                ->whereNull('credit_invoice_no')
                ->update([
                    'credit_invoice_no' => $best->inv_ref,
                ]);

            $stats['updated'] += $affected;

            if ($affected > 0) {
                $stats['log_entries'][] = [
                    'cn_order_id' => $cnId,
                    'cn_reference_no' => $best->cn_ref,
                    'inv_order_id' => $best->inv_id,
                    'inv_reference_no' => $best->inv_ref,
                    'match_reason' => $reason,
                    'item_seconds_diff' => $bestItemDiff,
                    'order_seconds_diff' => $bestOrderDiff,
                    'customer_code' => $best->customer_code,
                ];
            }
        }
    }

    return $stats;
}
