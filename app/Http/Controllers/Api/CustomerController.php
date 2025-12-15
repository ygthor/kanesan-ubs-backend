<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer; // Using the Customer model
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule; // Required for unique rule updates

// Assuming makeResponse() is a helper function available globally or via a trait/base controller.
// Example: if (!function_exists('makeResponse')) {
//    function makeResponse($status, $message, $data = null) {
//        $response = [
//            'status' => $status,
//            'message' => $message,
//        ];
//        if ($data !== null) {
//            $response['data'] = $data;
//        }
//        return response()->json($response, $status);
//    }
// }

class CustomerController extends Controller
{
    /**
     * Display a listing of the customers.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        
        $query = Customer::query();
        
        // KBS user has full access to all customers
        if ($user && ($user->username === 'KBS' || $user->email === 'KBS@kanesan.my')) {
            // No filtering needed for KBS user
        } else {
            // Filter by agent_no matching user's name
            $query->whereIn('agent_no', $user->name);
        }
        
        // Handle sorting
        $sortBy = $request->input('sort_by', 'created_at'); // Default to created_at
        $sortOrder = $request->input('sort_order', 'desc'); // Default to desc
        
        // Validate sort_by field
        $allowedSortFields = ['created_at', 'company_name', 'customer_code'];
        if (!in_array($sortBy, $allowedSortFields)) {
            $sortBy = 'created_at';
        }
        
        // Validate sort_order
        $sortOrder = strtolower($sortOrder);
        if (!in_array($sortOrder, ['asc', 'desc'])) {
            $sortOrder = 'desc';
        }
        
        // Apply sorting
        $query->orderBy($sortBy, $sortOrder);
        
        // Add secondary sort by company_name if not already the primary sort
        if ($sortBy !== 'company_name') {
            $query->orderBy('company_name', 'asc');
        }
        
        $customers = $query->with('users')->get();
        return makeResponse(200, 'Customers retrieved successfully.', $customers);
    }

    /**
     * Get distinct customer states
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStates(Request $request)
    {
        $user = auth()->user();
        
        $query = Customer::select('state')
            ->whereNotNull('state')
            ->where('state', '!=', '')
            ->distinct()
            ->orderBy('state', 'asc');
        
        // Filter by user's assigned customers (unless KBS user or admin role)
        if ($user && !hasFullAccess()) {
            $query->whereIn('agent_no', $user->name);
        }
        
        $states = $query->pluck('state')->toArray();
        return makeResponse(200, 'Customer states retrieved successfully.', $states);
    }

    /**
     * Store a newly created customer in storage.
     *
     * @bodyParam customer_code string required The unique code for the customer. Example: "CUST001"
     * @bodyParam company_name string required The name of the company. Example: "Tech Solutions Inc."
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $user = auth()->user();
        
        $validator = Validator::make($request->all(), [
            'customer_code' => 'sometimes|nullable|string|max:255|unique:customers,customer_code',
            'customer_type' => 'required|string|max:255|in:Creditor,Cash Sales,Cash',
            'company_name' => 'required|string|max:255',
            'company_name2' => 'nullable|string|max:255',
            'address1' => 'required|string|max:255',
            'address2' => 'nullable|string|max:255',
            'address3' => 'nullable|string|max:255',
            'postcode' => 'nullable|string|max:20',
            'state' => 'nullable|string|max:255',
            'territory' => 'nullable|string|max:255',
            'telephone1' => 'nullable|string|max:50',
            'telephone2' => 'nullable|string|max:50',
            'fax_no' => 'nullable|string|max:50',
            'contact_person' => 'nullable|string|max:255',
            'customer_group' => 'nullable|string|max:255',
            'lot_type' => 'nullable|string|max:255',
            // Add other fillable fields from your Customer model
            'email' => 'nullable|email|max:255|unique:customers,email',
            'phone' => 'nullable|string|max:50',
            'avatar_url' => 'nullable|string|max:2048',
            'payment_term' => 'nullable|string|max:255',
            'max_discount' => 'nullable|string|max:255', // Or numeric if you cast
            'segment' => 'nullable|string|max:255',
            'payment_type' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255', // General name field if used
            'address' => 'nullable|string|max:1000', // General address field if used
            'assigned_user_id' => 'nullable|integer|exists:users,id',
            'agent_no' => 'nullable|string|max:255', // WHY NEED THIS ? already asked to get from assigned_user_id right ?
        ]);

        if ($validator->fails()) {
            // Use custom response function for validation errors
            return makeResponse(422, 'Validation errors.', ['errors' => $validator->errors()]);
        }

        try {
            // Auto-generate customer_code if not provided or empty
            $customerCode = $request->input('customer_code');
            if (empty($customerCode) || trim($customerCode) === '') {
                $customerCode = $this->generateCustomerCode($request->input('customer_type'), $user->username);
            }
            
            // Ensure all fields from your Flutter form are included in $fillable in Customer model
            // and are passed here in $request->only([...])
            $customerData = $request->only([
                'company_name',
                'company_name2',
                'address1',
                'address2',
                'address3',
                'postcode',
                'state',
                'territory',
                'telephone1',
                'telephone2',
                'fax_no',
                'contact_person',
                'customer_group',
                'customer_type',
                'lot_type',
                'email',
                'phone',
                'avatar_url',
                'payment_term',
                'max_discount',
                'segment',
                'payment_type',
                'name',
                'address',
                'agent_no',
                // Ensure this list matches the $fillable array in your Customer model
                // and the fields sent from your Flutter "Detailed Form".
            ]);
            
            // Add the generated customer_code
            $customerData['customer_code'] = $customerCode;
            
            // Derive agent_no from assigned_user_id if provided, else creator
            if ($request->filled('assigned_user_id')) {
                $assignedUser = \App\Models\User::find($request->assigned_user_id);
                $customerData['agent_no'] = $assignedUser?->name ?? $assignedUser?->username ?? $customerData['agent_no'] ?? null;
            } else {
                $customerData['agent_no'] = $customerData['agent_no'] ?? ($user->name ?? $user->username ?? null);
            }
            
            $customer = Customer::create($customerData);

            // Handle user assignment using many-to-many relationship
            // Always link the customer to the user who created it
            $customer->users()->attach($user->id);
            
            // If admin assigned a different user, also link to that user
            if ($request->has('assigned_user_id') && $request->assigned_user_id && $request->assigned_user_id != $user->id) {
                // Admin assigned a different user, link both creator and assigned user
                $customer->users()->attach($request->assigned_user_id);
            }

            // Use custom response function for success
            return makeResponse(201, 'Customer created successfully.', $customer);
        } catch (\Exception $e) {
            // Log::error('Error creating customer: ' . $e->getMessage()); // Consider logging
            // Use custom response function for server errors
            return makeResponse(500, 'Failed to create customer.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Display the specified customer.
     *
     * @param  \App\Models\Customer  $customer
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Customer $customer)
    {
        $user = auth()->user();
        
        // Check if user has access to this customer
        if (!$this->userHasAccessToCustomer($user, $customer)) {
            return makeResponse(403, 'Access denied. You do not have permission to view this customer.', null);
        }
        
        $customer->load('users');
        return makeResponse(200, 'Customer retrieved successfully.', $customer);
    }

    /**
     * Display the specified customer by customer code.
     * Uses query parameter to handle customer codes with special characters like slashes (e.g., "3000/003")
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function showByCode(Request $request)
    {
        $user = auth()->user();
        
        // Get customer_code from query parameter
        $customerCode = $request->input('customer_code');
        
        if (!$customerCode) {
            return makeResponse(422, 'Customer code is required.', ['error' => 'customer_code parameter is missing']);
        }
        
        // Find customer by customer_code
        $customer = Customer::where('customer_code', $customerCode)->first();
        
        if (!$customer) {
            return makeResponse(404, 'Customer not found.', null);
        }
        
        // Check if user has access to this customer
        if (!$this->userHasAccessToCustomer($user, $customer)) {
            return makeResponse(403, 'Access denied. You do not have permission to view this customer.', null);
        }
        
        $customer->load('users');
        return makeResponse(200, 'Customer retrieved successfully.', $customer);
    }

    /**
     * Update the specified customer in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Customer  $customer
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Customer $customer)
    {
        $user = auth()->user();
        
        // Check if user has access to this customer
        if (!$this->userHasAccessToCustomer($user, $customer)) {
            return makeResponse(403, 'Access denied. You do not have permission to update this customer.', null);
        }
        
        $validator = Validator::make($request->all(), [
            'customer_code' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('customers')->ignore($customer->id),
            ],
            'company_name' => 'sometimes|required|string|max:255',
            'company_name2' => 'sometimes|nullable|string|max:255',
            'address1' => 'sometimes|string|max:255', // Made 'sometimes' to allow partial updates
            'address2' => 'nullable|string|max:255',
            'address3' => 'nullable|string|max:255',
            'postcode' => 'nullable|string|max:20',
            'state' => 'nullable|string|max:255',
            'territory' => 'nullable|string|max:255',
            'telephone1' => 'nullable|string|max:50',
            'telephone2' => 'nullable|string|max:50',
            'fax_no' => 'nullable|string|max:50',
            'contact_person' => 'nullable|string|max:255',
            'customer_group' => 'nullable|string|max:255',
            'customer_type' => 'nullable|string|max:255',
            'lot_type' => 'nullable|string|max:255',
            // Add other updatable fields and their validation
            'email' => ['sometimes', 'nullable', 'email', 'max:255', Rule::unique('customers')->ignore($customer->id)],
            'phone' => 'sometimes|nullable|string|max:50',
            'avatar_url' => 'sometimes|nullable|string|max:2048',
            'payment_term' => 'sometimes|nullable|string|max:255',
            'max_discount' => 'sometimes|nullable|string|max:255',
            'segment' => 'sometimes|nullable|string|max:255',
            'payment_type' => 'sometimes|nullable|string|max:255',
            'name' => 'sometimes|nullable|string|max:255',
            'address' => 'sometimes|nullable|string|max:1000',
            'assigned_user_id' => 'sometimes|nullable|integer|exists:users,id',
            'agent_no' => 'sometimes|nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            // Use custom response function for validation errors
            return makeResponse(422, 'Validation errors.', ['errors' => $validator->errors()]);
        }

        try {
            // Ensure all fields intended for update are in $request->only([...])
            // and are $fillable in the Customer model.
            $customer->update($request->only([
                'customer_code',
                'company_name',
                'company_name2',
                'address1',
                'address2',
                'address3',
                'postcode',
                'state',
                'territory',
                'telephone1',
                'telephone2',
                'fax_no',
                'contact_person',
                'customer_group',
                'customer_type',
                'lot_type',
                'email',
                'phone',
                'avatar_url',
                'payment_term',
                'max_discount',
                'segment',
                'payment_type',
                'name',
                'address',
                'agent_no',
            ]));

            // Handle user assignment using many-to-many relationship
            if ($request->has('assigned_user_id')) {
                if ($request->assigned_user_id) {
                    // Get existing user IDs to preserve creator link
                    $existingUserIds = $customer->users()->pluck('users.id')->toArray();
                    // Merge with new assignment (avoid duplicates)
                    $userIdsToSync = array_unique(array_merge($existingUserIds, [$request->assigned_user_id]));
                    // Sync to include both existing users and new assignment
                    $customer->users()->sync($userIdsToSync);
                }
                // Note: We don't remove all assignments if assigned_user_id is null/empty
                // to preserve the creator link. If you need to remove assignments, do it explicitly.
            }

            // Use custom response function for success
            return makeResponse(200, 'Customer updated successfully.', $customer);
        } catch (\Exception $e) {
            // Log::error('Error updating customer: ' . $e->getMessage()); // Consider logging
            // Use custom response function for server errors
            return makeResponse(500, 'Failed to update customer.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Remove the specified customer from storage.
     *
     * @param  \App\Models\Customer  $customer
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Customer $customer)
    {
        $user = auth()->user();
        
        // Check if user has access to this customer
        if (!$this->userHasAccessToCustomer($user, $customer)) {
            return makeResponse(403, 'Access denied. You do not have permission to delete this customer.', null);
        }
        
        try {
            $customer->delete();
            // Use custom response function for success, data can be null or an empty array
            return makeResponse(200, 'Customer deleted successfully.', null);
        } catch (\Exception $e) {
            // Log::error('Error deleting customer: ' . $e->getMessage()); // Consider logging
            // Use custom response function for server errors
            return makeResponse(500, 'Failed to delete customer.', ['error' => $e->getMessage()]);
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

    /**
     * Generate customer code based on customer type and username
     *
     * @param  string  $customerType
     * @param  string  $username
     * @return string
     */
    private function generateCustomerCode($customerType, $username)
    {
        $prefix = null;
        
        if ($customerType === 'Creditor') {
            $prefix = '3000';
        } elseif ($customerType === 'Cash Sales' || $customerType === 'Cash') {
            $usernameUpper = strtoupper($username);
            if ($usernameUpper === 'S01') {
                $prefix = '3010';
            } elseif ($usernameUpper === 'S02') {
                $prefix = '3020';
            } elseif ($usernameUpper === 'S03') {
                $prefix = '3030';
            } elseif ($usernameUpper === 'S04') {
                $prefix = '3040';
            } elseif ($usernameUpper === 'S05') {
                $prefix = '3050';
            } elseif ($usernameUpper === 'S06') {
                $prefix = '3060';
            } elseif ($usernameUpper === 'S07') {
                $prefix = '3070';
            } elseif ($usernameUpper === 'S08') {
                $prefix = '3080';
            } elseif ($usernameUpper === 'S09') {
                $prefix = '3090';
            } else {
                // Fallback for Cash Sales with invalid username
                throw new \Exception('Cash Sales is only available for users S01, S02, S03, S04, S05, S06, S07, S08, or S09. Your username: ' . $username);
            }
        } else {
            throw new \Exception('Invalid customer type: ' . $customerType);
        }
        
        // Find the next running number for this prefix
        $customers = Customer::where('customer_code', 'like', $prefix . '/%')
            ->pluck('customer_code')
            ->toArray();
        
        $nextNumber = 1;
        if (!empty($customers)) {
            $numbers = [];
            foreach ($customers as $code) {
                $codeParts = explode('/', $code);
                if (count($codeParts) === 2 && is_numeric($codeParts[1])) {
                    $numbers[] = (int)$codeParts[1];
                }
            }
            if (!empty($numbers)) {
                $nextNumber = max($numbers) + 1;
            }
        }
        
        // Format as 3000/001, 3000/002, etc.
        return $prefix . '/' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    }

}
