<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StockRequest;
use App\Models\StockRequestItem;
use App\Models\User;
use Illuminate\Http\Request;

class StockRequestController extends Controller
{
    /**
     * List all stock requests for admin.
     */
    public function index(Request $request)
    {
        $this->authorizeAdmin();

        $perPage = (int) $request->input('per_page', 20);
        if (!in_array($perPage, [20, 50, 100], true)) {
            $perPage = 20;
        }

        $query = StockRequest::with(['user', 'items'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->input('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->input('to_date'));
        }

        if ($request->filled('agent_id')) {
            $query->where('user_id', $request->integer('agent_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $requests = $query->paginate($perPage)->withQueryString();

        $agents = User::select('id', 'name', 'username', 'email')
            ->where(function ($query) {
                $query->where('username', '!=', 'KBS')
                    ->where('email', '!=', 'KBS@kanesan.my');
            })
            ->orderBy('name', 'asc')
            ->get()
            ->filter(function ($user) {
                return !$user->hasRole('admin');
            })
            ->values();

        return view('admin.stock-requests.index', compact('requests', 'agents', 'perPage'));
    }

    /**
     * Show a single stock request for admin to approve/reject.
     */
    public function show($id)
    {
        $this->authorizeAdmin();

        $stockRequest = StockRequest::with(['user', 'items', 'approvedBy'])->findOrFail($id);

        return view('admin.stock-requests.show', compact('stockRequest'));
    }

    /**
     * Admin approves a stock request, optionally adjusting quantities.
     */
    public function approve(Request $request, $id)
    {
        $this->authorizeAdmin();

        $stockRequest = StockRequest::with('items')->findOrFail($id);

        $validated = $request->validate([
            'admin_notes'             => 'nullable|string|max:500',
            'items'                   => 'required|array',
            'items.*.id'              => 'required|integer',
            'items.*.approved_qty'    => 'required|numeric|min:0',
        ]);

        // Update each item's approved qty
        $itemsKeyed = collect($validated['items'])->keyBy('id');
        foreach ($stockRequest->items as $item) {
            if (isset($itemsKeyed[$item->id])) {
                $item->update(['approved_qty' => $itemsKeyed[$item->id]['approved_qty']]);
            }
        }

        $stockRequest->update([
            'status'      => 'approved',
            'admin_notes' => $validated['admin_notes'] ?? null,
            'approved_at' => now(),
            'approved_by' => auth()->id(),
        ]);

        return redirect()->route('admin.stock-requests.index')
            ->with('success', 'Stock request #' . $id . ' has been approved.');
    }

    /**
     * Admin rejects a stock request.
     */
    public function reject(Request $request, $id)
    {
        $this->authorizeAdmin();

        $stockRequest = StockRequest::findOrFail($id);

        $validated = $request->validate([
            'admin_notes' => 'nullable|string|max:500',
        ]);

        $stockRequest->update([
            'status'      => 'rejected',
            'admin_notes' => $validated['admin_notes'] ?? null,
            'approved_at' => now(),
            'approved_by' => auth()->id(),
        ]);

        return redirect()->route('admin.stock-requests.index')
            ->with('success', 'Stock request #' . $id . ' has been rejected.');
    }

    private function authorizeAdmin()
    {
        $user = auth()->user();
        if (!$user || (!$user->hasRole('admin') && $user->username !== 'KBS' && $user->email !== 'KBS@kanesan.my')) {
            abort(403, 'Unauthorized.');
        }
    }
}
