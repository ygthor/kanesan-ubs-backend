<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StockRequest;
use App\Models\StockRequestItem;
use Illuminate\Http\Request;

class StockRequestController extends Controller
{
    /**
     * Agent submits a stock request.
     * POST /api/stock-requests
     * Body: { notes: string, items: [{ item_no, description, unit, requested_qty }] }
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'notes'                       => 'nullable|string|max:500',
            'items'                       => 'required|array|min:1',
            'items.*.item_no'             => 'required|string|max:50',
            'items.*.description'         => 'nullable|string|max:255',
            'items.*.unit'                => 'nullable|string|max:20',
            'items.*.requested_qty'       => 'required|numeric|min:0.0001',
        ]);

        $user = $request->user();

        $stockRequest = StockRequest::create([
            'user_id' => $user->id,
            'status'  => 'pending',
            'notes'   => $validated['notes'] ?? null,
        ]);

        foreach ($validated['items'] as $item) {
            StockRequestItem::create([
                'stock_request_id' => $stockRequest->id,
                'item_no'          => $item['item_no'],
                'description'      => $item['description'] ?? null,
                'unit'             => $item['unit'] ?? null,
                'requested_qty'    => $item['requested_qty'],
            ]);
        }

        $stockRequest->load('items');

        return response()->json([
            'error'   => false,
            'message' => 'Stock request submitted successfully.',
            'data'    => $stockRequest,
        ], 201);
    }

    /**
     * Agent views their own stock requests.
     * GET /api/stock-requests
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $requests = StockRequest::with('items')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'error' => false,
            'data'  => $requests,
        ]);
    }

    /**
     * Agent views a single stock request.
     * GET /api/stock-requests/{id}
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();

        $stockRequest = StockRequest::with('items')
            ->where('user_id', $user->id)
            ->findOrFail($id);

        return response()->json([
            'error' => false,
            'data'  => $stockRequest,
        ]);
    }
}
