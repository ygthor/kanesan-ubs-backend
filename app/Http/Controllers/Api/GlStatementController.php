<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class GlStatementController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        
        // Validate that a customer number is provided
        $validator = Validator::make($request->all(), [
            'CUSTNO' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $customerCode = $request->query('CUSTNO');
        
        // Check if user has access to this customer
        $customer = Customer::where('customer_code', $customerCode)->first();
        if (!$customer) {
            return makeResponse(404, 'Customer not found.', null);
        }
        
        if (!$this->userHasAccessToCustomer($user, $customer)) {
            return makeResponse(403, 'Access denied. You do not have permission to view this customer\'s GL statement.', null);
        }

        // IMPORTANT: Replace 'gldata' with your actual table name for General Ledger data.
        // IMPORTANT: Replace 'ACCNO' with the actual column name that stores the customer code.
        $query = DB::table('gldata')->where('ACCNO', $customerCode);

        // Order by creation date descending to show the most recent entries first
        // IMPORTANT: Replace 'CREATED_ON' if your date column has a different name.
        $query->orderBy('CREATED_ON', 'desc');

        // Paginate the results to prevent loading too much data at once
        $glEntries = $query->paginate(20); // You can adjust the number per page

        return makeResponse(200, 'GL Statement retrieved successfully.', $glEntries);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ACCNO' => 'required|string|max:50',
            'DESP' => 'required|string|max:255',
            'ACC_CODE' => 'nullable|string',
            'CAL1' => 'nullable|numeric',
            // Add validation for other fields as needed
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validatedData = $validator->validated();
        
        // Add timestamps
        $validatedData['CREATED_ON'] = Carbon::now();
        $validatedData['UPDATED_ON'] = Carbon::now();

        try {
            // IMPORTANT: Replace 'gldata' with your actual table name.
            $id = DB::table('gldata')->insertGetId($validatedData);
            $newEntry = DB::table('gldata')->find($id);

            return response()->json($newEntry, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create GL entry.', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        // IMPORTANT: Replace 'gldata' with your table name and 'ID' with your primary key column.
        $glEntry = DB::table('gldata')->where('ID', $id)->first();

        if (!$glEntry) {
            return response()->json(['error' => 'GL Entry not found.'], 404);
        }

        return response()->json($glEntry);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        // IMPORTANT: Replace 'gldata' with your table name and 'ID' with your primary key column.
        $glEntry = DB::table('gldata')->where('ID', $id)->first();

        if (!$glEntry) {
            return response()->json(['error' => 'GL Entry not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'DESP' => 'sometimes|required|string|max:255',
            'CAL1' => 'sometimes|numeric',
             // Add validation for other fields that can be updated
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $validatedData = $validator->validated();

        // Update the timestamp
        $validatedData['UPDATED_ON'] = Carbon::now();

        try {
            DB::table('gldata')->where('ID', $id)->update($validatedData);
            $updatedEntry = DB::table('gldata')->find($id);

            return response()->json($updatedEntry);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update GL entry.', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Check if user has access to a specific customer
     *
     * @param  \App\Models\User|null  $user
     * @param  \App\Models\Customer  $customer
     * @return bool
     */
    private function userHasAccessToCustomer($user, Customer $customer)
    {
        // KBS user has full access to all customers
        if ($user && ($user->username === 'KBS' || $user->email === 'KBS@kanesan.my')) {
            return true;
        }
        
        // Check if user is assigned to this customer
        if ($user && $user->customers()->where('customers.id', $customer->id)->exists()) {
            return true;
        }
        
        return false;
    }
}