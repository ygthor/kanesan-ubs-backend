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
        
        $query = Customer::orderBy('company_name', 'asc');
        
        // KBS user has full access to all customers
        if ($user && ($user->username === 'KBS' || $user->email === 'KBS@kanesan.my')) {
            // No filtering needed for KBS user
        } else {
            // Filter by allowed customer IDs if middleware has set them
            if ($request->has('allowed_customer_ids') && !empty($request->allowed_customer_ids)) {
                $query->whereIn('id', $request->allowed_customer_ids);
            } else {
                // If no allowed customer IDs, return empty result
                return makeResponse(200, 'No customers accessible.', []);
            }
        }
        
        $customers = $query->with('users')->get();
        return makeResponse(200, 'Customers retrieved successfully.', $customers);
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
        $validator = Validator::make($request->all(), [
            'customer_code' => 'required|string|max:255|unique:customers,customer_code',
            'company_name' => 'required|string|max:255',
            'company_name2' => 'nullable|string|max:255',
            'address1' => 'required|string|max:255',
            'address2' => 'nullable|string|max:255',
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
        ]);

        if ($validator->fails()) {
            // Use custom response function for validation errors
            return makeResponse(422, 'Validation errors.', ['errors' => $validator->errors()]);
        }

        try {
            // Ensure all fields from your Flutter form are included in $fillable in Customer model
            // and are passed here in $request->only([...])
            $customer = Customer::create($request->only([
                'customer_code',
                'company_name',
                'company_name2',
                'address1',
                'address2',
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
                'address'
                // Ensure this list matches the $fillable array in your Customer model
                // and the fields sent from your Flutter "Detailed Form".
            ]));

            // Handle user assignment using many-to-many relationship
            if ($request->has('assigned_user_id') && $request->assigned_user_id) {
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
                'address'
            ]));

            // Handle user assignment using many-to-many relationship
            if ($request->has('assigned_user_id')) {
                if ($request->assigned_user_id) {
                    // Sync the user assignment (replace existing assignments)
                    $customer->users()->sync([$request->assigned_user_id]);
                } else {
                    // Remove all user assignments if assigned_user_id is null/empty
                    $customer->users()->detach();
                }
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

}
