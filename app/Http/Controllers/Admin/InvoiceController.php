<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Customer;
use Illuminate\Http\Request;

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

        $query = Order::with('customer');

        // Apply date filter
        if ($fromDate && $toDate) {
            if (strlen($fromDate) == 10) {
                $fromDate .= ' 00:00:00';
            }
            if (strlen($toDate) == 10) {
                $toDate .= ' 23:59:59';
            }
            $query->whereBetween('order_date', [$fromDate, $toDate]);
        }

        // Filter by type
        if ($type) {
            $query->where('type', $type);
        }

        // Filter by customer
        if ($customerId) {
            $query->where('customer_id', $customerId);
        }

        // Order by date desc, then id desc
        $orders = $query->orderBy('order_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(20);

        // Get customers for filter dropdown
        $customers = Customer::orderBy('customer_code', 'asc')->get();

        return view('admin.invoices.index', compact('orders', 'fromDate', 'toDate', 'customerId', 'type', 'customers'));
    }
}
