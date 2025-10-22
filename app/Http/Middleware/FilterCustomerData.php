<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class FilterCustomerData
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        
        // If user is KBS admin, allow access to all customer data
        if ($user && ($user->username === 'KBS' || $user->email === 'KBS@kanesan.my')) {
            return $next($request);
        }
        
        // If user is authenticated, filter customer data based on assignments
        if ($user) {
            // Get customer IDs assigned to this user
            $assignedCustomerIds = $user->customers()->pluck('customers.id')->toArray();
            
            // Store the customer IDs in the request for controllers to use
            $request->merge(['allowed_customer_ids' => $assignedCustomerIds]);
        }
        
        return $next($request);
    }
}
