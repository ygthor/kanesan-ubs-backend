<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
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
        $type = $request->input('type');
        $referenceNo = $request->input('reference_no');
        $agentNo = $request->input('agent_no');

        $query = Order::with('customer');

        // Apply date filter
        if ($fromDate && $toDate) {
            if (strlen($fromDate) == 10) {
                $fromDatetime = $fromDate . ' 00:00:00';
            }
            if (strlen($toDate) == 10) {
                $toDatetime = $toDate . ' 23:59:59';
            }
            $query->whereBetween('order_date', [$fromDatetime, $toDatetime]);
        }

        // Filter by type
        if ($type) {
            $query->where('type', $type);
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
}
