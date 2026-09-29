<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Icitem;
use Illuminate\Http\Request;

class ItemManagementController extends Controller
{
    /**
     * Display a listing of items with active/inactive status management.
     */
    public function index(Request $request)
    {
        $this->authorizeAdmin();

        $totalCount = Icitem::count();
        $activeCount = Icitem::active()->count();
        $inactiveCount = Icitem::inactive()->count();

        // Get unique groups for the filter dropdown
        $groups = Icitem::whereNotNull('GROUP')
            ->where('GROUP', '!=', '')
            ->distinct()
            ->orderBy('GROUP')
            ->pluck('GROUP');

        $search = trim($request->input('search', ''));
        $selectedGroup = trim($request->input('group', ''));
        $selectedStatus = trim($request->input('status', 'all'));
        $perPage = (int) $request->input('per_page', 25);

        if (!in_array($perPage, [10, 25, 50, 100, 250])) {
            $perPage = 25;
        }

        $query = Icitem::query();

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(ITEMNO) LIKE ?', ['%' . strtolower($search) . '%'])
                    ->orWhereRaw('LOWER(DESP) LIKE ?', ['%' . strtolower($search) . '%']);
            });
        }

        if (!empty($selectedGroup)) {
            $query->where('GROUP', $selectedGroup);
        }

        if ($selectedStatus === 'active') {
            $query->active();
        } elseif ($selectedStatus === 'inactive') {
            $query->inactive();
        }

        $items = $query->orderBy('GROUP')
            ->orderBy('ITEMNO')
            ->paginate($perPage)
            ->withQueryString();

        return view('admin.items.index', compact(
            'items',
            'groups',
            'totalCount',
            'activeCount',
            'inactiveCount',
            'search',
            'selectedGroup',
            'selectedStatus',
            'perPage'
        ));
    }

    /**
     * Toggle active/inactive status for a single item.
     */
    public function toggleStatus(Request $request, string $itemno)
    {
        $this->authorizeAdmin();

        $item = Icitem::where('ITEMNO', $itemno)->firstOrFail();

        if ($request->has('status')) {
            $requestedStatus = strtolower($request->input('status'));
            $newStat = ($requestedStatus === 'inactive') ? 'INACTIVE' : 'ACTIVE';
        } else {
            // Toggle current status
            $newStat = $item->is_active ? 'INACTIVE' : 'ACTIVE';
        }

        $item->ITEM_STAT = $newStat;
        $item->UPDATED_BY = substr(auth()->user()->username ?? auth()->user()->name ?? 'ADMIN', 0, 8);
        $item->UPDATED_ON = now()->format('Y-m-d H:i:s');
        $item->save();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Item {$item->ITEMNO} is now " . ($item->is_active ? 'Active' : 'Inactive') . '.',
                'itemno' => $item->ITEMNO,
                'item_stat' => $item->ITEM_STAT,
                'is_active' => $item->is_active,
                'active_count' => Icitem::active()->count(),
                'inactive_count' => Icitem::inactive()->count(),
            ]);
        }

        return redirect()->back()->with('success', "Item {$item->ITEMNO} is now " . ($item->is_active ? 'Active' : 'Inactive') . '.');
    }

    /**
     * Batch update status for multiple items.
     */
    public function batchUpdateStatus(Request $request)
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'item_nos' => 'required|array|min:1',
            'item_nos.*' => 'string',
            'status' => 'required|in:active,inactive',
        ]);

        $newStat = ($validated['status'] === 'inactive') ? 'INACTIVE' : 'ACTIVE';
        $updatedBy = substr(auth()->user()->username ?? auth()->user()->name ?? 'ADMIN', 0, 8);
        $updatedOn = now()->format('Y-m-d H:i:s');

        $count = Icitem::whereIn('ITEMNO', $validated['item_nos'])->update([
            'ITEM_STAT' => $newStat,
            'UPDATED_BY' => $updatedBy,
            'UPDATED_ON' => $updatedOn,
        ]);

        $label = ($newStat === 'INACTIVE') ? 'Inactive' : 'Active';

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Successfully marked {$count} item(s) as {$label}.",
                'count' => $count,
                'active_count' => Icitem::active()->count(),
                'inactive_count' => Icitem::inactive()->count(),
            ]);
        }

        return redirect()->back()->with('success', "Successfully marked {$count} item(s) as {$label}.");
    }

    /**
     * Check admin authorization.
     */
    private function authorizeAdmin(): void
    {
        $user = auth()->user();
        if (!$user || (!$user->hasRole('admin') && $user->username !== 'KBS' && $user->email !== 'KBS@kanesan.my')) {
            abort(403, 'Unauthorized access. Item Management is only available for administrators and KBS users.');
        }
    }
}
