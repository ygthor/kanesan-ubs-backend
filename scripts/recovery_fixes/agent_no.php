<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

function recoveryAgentNoFromReferenceNo(string $referenceNo): ?string
{
    $referenceNo = strtoupper(trim($referenceNo));
    if (!preg_match('/^S([0-9])(?:C)?[0-9]+$/', $referenceNo, $matches)) {
        return null;
    }

    return 'S' . str_pad($matches[1], 2, '0', STR_PAD_LEFT);
}

function recoveryFixMissingOrderAgentNo(bool $dryRun): array
{
    println('Fix: missing orders.agent_no from reference_no');

    $rows = DB::table('orders')
        ->select(['id', 'reference_no', 'agent_no'])
        ->where('reference_no', 'like', 'S%')
        ->orderBy('id')
        ->get();

    $stats = [
        'planned' => 0,
        'updated' => 0,
        'skipped_invalid_reference' => 0,
        'log_entries' => [],
    ];

    foreach ($rows as $row) {
        $agentNo = recoveryAgentNoFromReferenceNo((string) $row->reference_no);

        if ($agentNo === null) {
            $stats['skipped_invalid_reference']++;
            println("  SKIP invalid reference: order {$row->id} reference_no={$row->reference_no}");
            continue;
        }

        if ((string) $row->agent_no === $agentNo) {
            continue;
        }

        $stats['planned']++;
        println("  FIX order {$row->id} {$row->reference_no}: agent_no={$row->agent_no} -> {$agentNo}");

        if (!$dryRun) {
            $affected = DB::table('orders')
                ->where('id', $row->id)
                ->where(function ($query) use ($row) {
                    if ($row->agent_no === null) {
                        $query->whereNull('agent_no');
                    } else {
                        $query->where('agent_no', $row->agent_no);
                    }
                })
                ->update([
                    'agent_no' => $agentNo,
                ]);

            $stats['updated'] += $affected;

            if ($affected > 0) {
                $stats['log_entries'][] = [
                    'order_id' => $row->id,
                    'reference_no' => $row->reference_no,
                    'old_agent_no' => $row->agent_no,
                    'new_agent_no' => $agentNo,
                ];
            }
        }
    }

    return $stats;
}
