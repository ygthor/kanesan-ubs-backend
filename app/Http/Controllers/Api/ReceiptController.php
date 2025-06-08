<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Receipt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ReceiptController extends Controller
{
    /**
     * Display a listing of receipts.
     */
    public function index(Request $request)
    {
        $receipts = Receipt::with('customer:id,customer_code,company_name') // Eager load customer info
                         ->orderBy('receipt_date', 'desc')
                         ->paginate($request->input('per_page', 15));

        return makeResponse(200, 'Receipts retrieved successfully.', $receipts);
    }

    /**
     * Store a newly created receipt in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'receipt_no' => 'required|string|max:255|unique:receipts,receipt_no',
            'customer_id' => 'required|exists:customers,id',
            'customer_name' => 'required|string|max:255',
            'customer_code' => 'required|string|max:255',
            'receipt_date' => 'required|date',
            'payment_type' => 'required|string|max:255',
            'debt_amount' => 'required|numeric|min:0',
            'transaction_amount' => 'required|numeric|min:0',
            'paid_amount' => 'required|numeric|min:0',
            'payment_reference_no' => 'nullable|string|max:255',
            'cheque_no' => 'nullable|required_if:payment_type,Cheque|string|max:255',
            'cheque_type' => 'nullable|required_if:payment_type,Cheque|string|max:255',
            'bank_name' => 'nullable|required_if:payment_type,Cheque|string|max:255',
        ]);

        if ($validator->fails()) {
            return makeResponse(422, 'Validation errors.', ['errors' => $validator->errors()]);
        }

        try {
            $receipt = Receipt::create($validator->validated());
            return makeResponse(201, 'Receipt created successfully.', $receipt);
        } catch (\Exception $e) {
            // Log::error('Receipt creation failed: ' . $e->getMessage());
            return makeResponse(500, 'Failed to create receipt.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Display the specified receipt.
     */
    public function show(Receipt $receipt)
    {
        $receipt->load('customer'); // Load related customer
        return makeResponse(200, 'Receipt retrieved successfully.', $receipt);
    }

    /**
     * Update the specified receipt in storage.
     */
    public function update(Request $request, Receipt $receipt)
    {
        $validator = Validator::make($request->all(), [
            'receipt_no' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('receipts')->ignore($receipt->id)],
            'customer_id' => 'sometimes|required|exists:customers,id',
            'customer_name' => 'sometimes|required|string|max:255',
            'customer_code' => 'sometimes|required|string|max:255',
            'receipt_date' => 'sometimes|required|date',
            'payment_type' => 'sometimes|required|string|max:255',
            'debt_amount' => 'sometimes|required|numeric|min:0',
            'transaction_amount' => 'sometimes|required|numeric|min:0',
            'paid_amount' => 'sometimes|required|numeric|min:0',
            'payment_reference_no' => 'nullable|string|max:255',
            'cheque_no' => 'nullable|required_if:payment_type,Cheque|string|max:255',
            'cheque_type' => 'nullable|required_if:payment_type,Cheque|string|max:255',
            'bank_name' => 'nullable|required_if:payment_type,Cheque|string|max:255',
        ]);

        if ($validator->fails()) {
            return makeResponse(422, 'Validation errors.', ['errors' => $validator->errors()]);
        }

        try {
            $receipt->update($validator->validated());
            return makeResponse(200, 'Receipt updated successfully.', $receipt);
        } catch (\Exception $e) {
            // Log::error('Receipt update failed: ' . $e->getMessage());
            return makeResponse(500, 'Failed to update receipt.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Remove the specified receipt from storage.
     */
    public function destroy(Receipt $receipt)
    {
        try {
            $receipt->delete();
            return makeResponse(200, 'Receipt deleted successfully.', null);
        } catch (\Exception $e) {
            // Log::error('Receipt deletion failed: ' . $e->getMessage());
            return makeResponse(500, 'Failed to delete receipt.', ['error' => $e->getMessage()]);
        }
    }
}
