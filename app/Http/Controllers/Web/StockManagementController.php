<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class StockManagementController extends Controller
{
    /**
     * Display the stock management page.
     * Only accessible by admin or KBS users.
     */
    public function index()
    {
        $user = auth()->user();
        
        // Check if user is admin or KBS
        if (!$user || (!$user->hasRole('admin') && $user->username !== 'KBS' && $user->email !== 'KBS@kanesan.my')) {
            abort(403, 'Unauthorized access. Stock Management is only available for administrators and KBS users.');
        }
        
        return view('inventory.stock-management');
    }
}
