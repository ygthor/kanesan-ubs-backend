<?php

namespace App\Services;

use App\Models\ItemTransaction;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BusinessReportService
{
    /*
    params:
    - from_date
    - to_date
    - agent_no
    - customer_id
    
    */
    public function getTradeReturns($params)
    {
        $fromDate = $params['from_date'];
        $toDate = $params['to_date'];
        $agentNo = $params['agent_no'];
        $customerId = $params['customer_id'];

        if (empty($fromDate)) {
            $fromDate = date('Y-01-01');
        }
        if (empty($toDate)) {
            $toDate = date('Y-m-d');
        }

        if (!$fromDate || !$toDate) {
            return response()->json(['error' => 'from_date and to_date are required'], 422);
        }

        // Returns: Credit Notes (type='CN')
        $returnsQuery = DB::table('orders AS cn_orders')
            ->selectRaw('
                customers.customer_type,
                cn_orders.reference_no,
                cn_orders.credit_invoice_no,
                COUNT(inv_order_items.id) AS inv_count,
                cn_orders.net_amount
            ')
            ->join('customers', 'cn_orders.customer_id', '=', 'customers.id')
            ->leftJoin('order_items AS inv_order_items', function ($join) {
                $join->on('inv_order_items.reference_no', '=', 'cn_orders.credit_invoice_no');
            })
            ->whereBetween('cn_orders.order_date', [$fromDate, $toDate])
            ->where('cn_orders.type', 'CN')
            ->groupBy(
                'cn_orders.reference_no',
                'cn_orders.credit_invoice_no',
                'customers.customer_type',
                'cn_orders.net_amount'
            );

        // Filter by agent_no directly on orders table (if user doesn't have full access)
        if ($agentNo) {
            $returnsQuery->where('cn_orders.agent_no', $agentNo);
        }
        if ($customerId) {
            $returnsQuery->where('cn_orders.customer_id', $customerId);
        }
        $returns = $returnsQuery->get();

        $CR_withInv = 0;
        $CA_withInv = 0;
        $CR_withoutInv = 0;
        $CA_withoutInv = 0;

        foreach ($returns as $return) {
            if ($return->customer_type == 'CREDITOR') {
                if ($return->inv_count > 0) {
                    $CR_withInv += $return->net_amount;
                } else {
                    $CR_withoutInv += $return->net_amount;
                }
            } else {
                if ($return->inv_count > 0) {
                    $CA_withInv += $return->net_amount;
                } else {
                    $CA_withoutInv += $return->net_amount;
                }
            }
        }

        

        return [
            'Credit_withInv' => $CR_withInv,
            'Cash_withInv' => $CA_withInv,
            'Credit_withoutInv' => $CR_withoutInv,
            'Cash_withoutInv' => $CA_withoutInv,
        ];
    }
}
