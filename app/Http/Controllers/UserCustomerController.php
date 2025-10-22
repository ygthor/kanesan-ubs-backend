<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Customer;
use App\Models\UserCustomer;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class UserCustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $userCustomers = UserCustomer::with(['user', 'customer'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('admin.user-customers.index', compact('userCustomers'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $users = User::orderBy('name')->get();
        $customers = Customer::orderBy('name')->get();
        
        return view('admin.user-customers.create', compact('users', 'customers'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'customer_ids' => 'required|array|min:1',
            'customer_ids.*' => 'exists:customers,id',
        ]);

        $userId = $request->user_id;
        $customerIds = $request->customer_ids;
        
        // Get existing assignments for this user
        $existingAssignments = UserCustomer::where('user_id', $userId)
            ->whereIn('customer_id', $customerIds)
            ->pluck('customer_id')
            ->toArray();
        
        // Filter out already assigned customers
        $newCustomerIds = array_diff($customerIds, $existingAssignments);
        
        if (empty($newCustomerIds)) {
            return redirect()->back()
                ->with('error', 'All selected customers are already assigned to this user.')
                ->withInput();
        }
        
        // Create new assignments
        $assignments = [];
        foreach ($newCustomerIds as $customerId) {
            $assignments[] = [
                'user_id' => $userId,
                'customer_id' => $customerId,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        
        UserCustomer::insert($assignments);
        
        $successMessage = count($newCustomerIds) . ' customer(s) assigned successfully.';
        if (count($existingAssignments) > 0) {
            $successMessage .= ' ' . count($existingAssignments) . ' customer(s) were already assigned.';
        }

        return redirect()->route('admin.user-customers.index')
            ->with('success', $successMessage);
    }

    /**
     * Display the specified resource.
     */
    public function show(UserCustomer $userCustomer): View
    {
        $userCustomer->load(['user', 'customer']);
        return view('admin.user-customers.show', compact('userCustomer'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(UserCustomer $userCustomer): View
    {
        $users = User::orderBy('name')->get();
        $customers = Customer::orderBy('name')->get();
        
        // Load the user's customers for the edit view
        $userCustomer->load(['user.customers']);
        
        return view('admin.user-customers.edit', compact('userCustomer', 'users', 'customers'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, UserCustomer $userCustomer): RedirectResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'customer_id' => 'required|exists:customers,id',
        ]);

        // Check if assignment already exists (excluding current record)
        $existing = UserCustomer::where('user_id', $request->user_id)
            ->where('customer_id', $request->customer_id)
            ->where('id', '!=', $userCustomer->id)
            ->first();

        if ($existing) {
            return redirect()->back()
                ->with('error', 'This user is already assigned to this customer.')
                ->withInput();
        }

        $userCustomer->update($request->only(['user_id', 'customer_id']));

        return redirect()->route('admin.user-customers.index')
            ->with('success', 'User-customer assignment updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(UserCustomer $userCustomer): RedirectResponse
    {
        $userCustomer->delete();

        return redirect()->route('admin.user-customers.index')
            ->with('success', 'User-customer assignment deleted successfully.');
    }

    /**
     * Show customer assignments for a specific user.
     */
    public function userCustomers(User $user): View
    {
        // Load user with their customer assignments including pivot data
        $user->load(['customers' => function ($query) {
            $query->withPivot('id', 'created_at', 'updated_at');
        }]);
        
        // Get all customers for the dropdown
        $customers = Customer::orderBy('name')->get();
        
        return view('admin.users.customers', compact('user', 'customers'));
    }

    /**
     * Store customer assignments for a specific user.
     */
    public function storeUserCustomers(Request $request, User $user): RedirectResponse
    {
        $request->validate([
            'customer_ids' => 'required|array|min:1',
            'customer_ids.*' => 'exists:customers,id',
        ]);

        $customerIds = $request->customer_ids;
        
        // Get existing assignments for this user
        $existingAssignments = $user->customers()->whereIn('customers.id', $customerIds)->pluck('customers.id')->toArray();
        
        // Filter out already assigned customers
        $newCustomerIds = array_diff($customerIds, $existingAssignments);
        
        if (empty($newCustomerIds)) {
            return redirect()->back()
                ->with('error', 'All selected customers are already assigned to this user.')
                ->withInput();
        }
        
        // Create new assignments
        $assignments = [];
        foreach ($newCustomerIds as $customerId) {
            $assignments[] = [
                'user_id' => $user->id,
                'customer_id' => $customerId,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        
        UserCustomer::insert($assignments);
        
        $successMessage = count($newCustomerIds) . ' customer(s) assigned successfully.';
        if (count($existingAssignments) > 0) {
            $successMessage .= ' ' . count($existingAssignments) . ' customer(s) were already assigned.';
        }

        return redirect()->route('admin.users.customers', $user)
            ->with('success', $successMessage);
    }

    /**
     * Remove a customer assignment for a specific user.
     */
    public function destroyUserCustomer(User $user, UserCustomer $userCustomer): RedirectResponse
    {
        // Verify the assignment belongs to the user
        if ($userCustomer->user_id !== $user->id) {
            return redirect()->route('admin.users.customers', $user)
                ->with('error', 'Invalid assignment.');
        }

        $userCustomer->delete();

        return redirect()->route('admin.users.customers', $user)
            ->with('success', 'Customer assignment removed successfully.');
    }
}
