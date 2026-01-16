<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Customer;
use App\Models\EInvoiceRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    /**
     * Check if user is admin or KBS
     */
    private function checkAccess()
    {
        $user = auth()->user();
        if (!$user || (!$user->hasRole('admin') && $user->username !== 'KBS' && $user->email !== 'KBS@kanesan.my')) {
            abort(403, 'Unauthorized access. Invoices are only available for administrators and KBS users.');
        }
    }

    /**
     * Display a listing of invoices (orders) with filters.
     */
    public function index(Request $request)
    {
        $this->checkAccess();

        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
        $customerId = $request->input('customer_id');
        $type = $request->input('type'); // Allow multiple types
        $referenceNo = $request->input('reference_no');
        $agentNo = $request->input('agent_no');

        $query = Order::with('customer');

        // Apply date filter
        if ($fromDate && $toDate) {
            $fromDateForQuery = $fromDate;
            $toDateForQuery = $toDate;
            if (strlen($fromDateForQuery) == 10) {
                $fromDateForQuery .= ' 00:00:00';
            }
            if (strlen($toDateForQuery) == 10) {
                $toDateForQuery .= ' 23:59:59';
            }
            $query->whereBetween('order_date', [$fromDateForQuery, $toDateForQuery]);
        }

        // Filter by type (multiple selection)
        if ($type && !empty(array_filter($type))) {
            $query->whereIn('type', $type);
        }

        // Filter by customer
        if ($customerId) {
            $query->where('customer_id', $customerId);
        }

        // Filter by reference no (partial match)
        if ($referenceNo) {
            $query->where('reference_no', 'like', '%' . $referenceNo . '%');
        }

        // Filter by agent no (partial match)
        if ($agentNo) {
            $query->where('agent_no', 'like', '%' . $agentNo . '%');
        }

        // Order by date desc, then id desc
        $orders = $query
            // ->orderBy('id', 'desc')
            // ->orderBy('order_date', 'desc')
            ->orderBy('reference_no', 'desc')
            ->paginate(200)
            ->withQueryString();;

        // Get customers for filter dropdown
        $customers = Customer::orderBy('customer_code', 'asc')->get();

        // Ensure type is always an array for the view
        if (!is_array($type)) {
            $type = $type ? [$type] : [];
        }

        return view('admin.invoices.index', compact('orders', 'fromDate', 'toDate', 'customerId', 'type', 'referenceNo', 'agentNo', 'customers'));
    }

    /**
     * Display detailed view of an invoice/order.
     */
    public function show($id)
    {
        $this->checkAccess();

        $order = Order::with(['customer', 'items'])
            ->findOrFail($id);

        // Get e-invoice request if exists
        $eInvoiceRequest = EInvoiceRequest::where('invoice_no', $order->reference_no)
            ->where('customer_code', $order->customer_code)
            ->first();

        // Get receipts linked to this order
        $receipts = \DB::table('receipt_orders')
            ->join('receipts', 'receipt_orders.receipt_id', '=', 'receipts.id')
            ->where('receipt_orders.order_refno', $order->reference_no)
            ->whereNull('receipts.deleted_at')
            ->select('receipts.*', 'receipt_orders.amount_applied')
            ->orderBy('receipts.receipt_date', 'desc')
            ->get();

        // Get linked credit notes (if this is an invoice)
        $linkedCreditNotes = [];
        if ($order->type == 'INV') {
            $linkedCreditNotes = Order::where('type', 'CN')
                ->where('credit_invoice_no', $order->reference_no)
                ->with('customer')
                ->orderBy('order_date', 'desc')
                ->get();
        }

        // Get linked invoice (if this is a credit note)
        $linkedInvoice = null;
        if ($order->type == 'CN' && $order->credit_invoice_no) {
            $linkedInvoice = Order::where('reference_no', $order->credit_invoice_no)
                ->with('customer')
                ->first();
        }

        return view('admin.invoices.show', compact('order', 'eInvoiceRequest', 'receipts', 'linkedCreditNotes', 'linkedInvoice'));
    }

    /**
     * Display invoice resync page with same filters as index.
     */
    public function resync(Request $request)
    {
        $this->checkAccess();

        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
        $customerId = $request->input('customer_id');
        $type = $request->input('type', ['INV', 'CN']); // Default to INV and CN
        $referenceNo = $request->input('reference_no');
        $agentNo = $request->input('agent_no');
        $perPage = $request->input('per_page', 15); // Default to 15

        // Validate per_page to prevent abuse
        if (!in_array($perPage, [15, 100, 500, 1000])) {
            $perPage = 15;
        }

        $query = Order::with('customer');

        // Apply date filter
        if ($fromDate && $toDate) {
            $fromDateForQuery = $fromDate;
            $toDateForQuery = $toDate;
            if (strlen($fromDateForQuery) == 10) {
                $fromDateForQuery .= ' 00:00:00';
            }
            if (strlen($toDateForQuery) == 10) {
                $toDateForQuery .= ' 23:59:59';
            }
            $query->whereBetween('order_date', [$fromDateForQuery, $toDateForQuery]);
        }

        // Filter by type (multiple selection)
        if ($type && !empty(array_filter($type))) {
            $query->whereIn('type', $type);
        }

        // Filter by customer
        if ($customerId) {
            $query->where('customer_id', $customerId);
        }

        // Filter by reference no (exact match using comma-separated list with WHERE IN)
        if ($referenceNo) {
            // Parse comma-separated values
            $refNos = array_map('trim', explode(',', $referenceNo));
            // Remove empty values
            $refNos = array_filter($refNos);
            
            if (!empty($refNos)) {
                $query->whereIn('reference_no', $refNos);
            }
        }

        // Filter by agent no (partial match)
        if ($agentNo) {
            $query->where('agent_no', 'like', '%' . $agentNo . '%');
        }

        // Order by date desc, then id desc
        $orders = $query
            ->orderBy('updated_at', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        // Get customers for filter dropdown
        $customers = Customer::orderBy('customer_code', 'asc')->get();

        // Ensure type is always an array for the view
        if (!is_array($type)) {
            $type = $type ? [$type] : ['INV', 'CN'];
        }

        return view('admin.invoices.resync', compact('orders', 'fromDate', 'toDate', 'customerId', 'type', 'referenceNo', 'agentNo', 'customers', 'perPage'));
    }

    /**
     * Update modification date (updated_at) for selected invoices to trigger UBS sync.
     */
    public function updateModificationDate(Request $request)
    {
        $this->checkAccess();

        $orderIds = $request->input('order_ids', []);

        if (empty($orderIds)) {
            return response()->json([
                'success' => false,
                'message' => 'No invoices selected.'
            ], 400);
        }

        try {
            // Get reference numbers for the selected orders
            $referenceNos = Order::whereIn('id', $orderIds)->pluck('reference_no')->toArray();

            // Update orders
            $updatedOrders = Order::whereIn('id', $orderIds)
                ->update(['updated_at' => now()]);

            // Update order items linked by reference_no
            $updatedOrderItems = OrderItem::whereIn('reference_no', $referenceNos)
                ->update(['updated_at' => now()]);

            $totalUpdated = $updatedOrders + $updatedOrderItems;

            return response()->json([
                'success' => true,
                'message' => "Successfully updated modification date for {$updatedOrders} invoice(s) and {$updatedOrderItems} item(s). Total: {$totalUpdated} records.",
                'updated_orders' => $updatedOrders,
                'updated_order_items' => $updatedOrderItems,
                'total_updated' => $totalUpdated
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating invoices: ' . $e->getMessage()
            ], 500);
        }
    }
}
