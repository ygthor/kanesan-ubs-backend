<?php

use App\Models\UBS\AccArCust;
use App\Models\Icitem;
use App\Models\ItemTransaction;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect('/dashboard');
    }
    return redirect('/login');
});

// Authentication routes
Route::get('/login', [\App\Http\Controllers\Auth\AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [\App\Http\Controllers\Auth\AuthController::class, 'login']);
Route::post('/logout', [\App\Http\Controllers\Auth\AuthController::class, 'logout'])->name('logout');

// Dashboard route (requires authentication)
Route::get('/dashboard', [\App\Http\Controllers\Auth\AuthController::class, 'dashboard'])->name('dashboard')->middleware('auth');

// E-Invoice Request Form (public route, no authentication required)
Route::get('/e-invoice', [\App\Http\Controllers\EInvoiceController::class, 'showForm'])->name('e-invoice.form');
Route::post('/e-invoice', [\App\Http\Controllers\EInvoiceController::class, 'submitForm'])->name('e-invoice.submit');

// Test email route (for testing purposes)
Route::get('/test/e-invoice-email', [\App\Http\Controllers\EInvoiceController::class, 'testEmail'])->name('test.e-invoice-email');

// Stock Management routes (requires authentication and admin/KBS access - checked in controller)
Route::middleware(['auth'])->prefix('inventory')->name('inventory.')->group(function () {
    Route::get('/stock-management', [\App\Http\Controllers\Admin\StockManagementController::class, 'index'])->name('stock-management');
    Route::get('/stock-management/create', [\App\Http\Controllers\Admin\StockManagementController::class, 'create'])->name('stock-management.create');
    Route::post('/stock-management', [\App\Http\Controllers\Admin\StockManagementController::class, 'store'])->name('stock-management.store');
    Route::get('/stock-management/item/{itemno}/transactions', [\App\Http\Controllers\Admin\StockManagementController::class, 'showItemTransactions'])->name('stock-management.item.transactions');
    Route::get('/stock-management/stock-by-agent/{itemno}', [\App\Http\Controllers\Admin\StockManagementController::class, 'getStockByAgentWeb'])->name('stock-management.stock-by-agent');
    Route::post('/stock-management/opening-balance', [\App\Http\Controllers\Admin\StockManagementController::class, 'storeOpeningBalance'])->name('stock-management.opening-balance.store');
    Route::put('/stock-management/opening-balance/{id}', [\App\Http\Controllers\Admin\StockManagementController::class, 'updateOpeningBalance'])->name('stock-management.opening-balance.update');
    Route::delete('/stock-management/opening-balance/{id}', [\App\Http\Controllers\Admin\StockManagementController::class, 'deleteOpeningBalance'])->name('stock-management.opening-balance.delete');
    Route::get('/item-movements', [\App\Http\Controllers\Admin\StockManagementController::class, 'itemMovements'])->name('item-movements');

    // Export routes for stock management
    Route::get('/stock-management/export/excel', [\App\Http\Controllers\Admin\StockManagementController::class, 'exportExcel'])->name('stock-management.export.excel');
    Route::get('/stock-management/export/pdf', [\App\Http\Controllers\Admin\StockManagementController::class, 'exportPdf'])->name('stock-management.export.pdf');
});

// Demo route for testing
Route::get('/demo-users', function() {
    return view('demo-users');
})->name('demo-users')->middleware('auth');

// Debug route for order items and item transactions tally
Route::get('/debug/order-items-transactions', [\App\Http\Controllers\Web\DebugController::class, 'orderItemsTransactions'])->name('debug.order-items-transactions')->middleware('auth');
Route::post('/debug/create-transaction', [\App\Http\Controllers\Web\DebugController::class, 'createMissingTransaction'])->name('debug.create-transaction')->middleware('auth');
Route::post('/debug/delete-transaction', [\App\Http\Controllers\Web\DebugController::class, 'deleteTransaction'])->name('debug.delete-transaction')->middleware('auth');

// Admin routes (require authentication and permissions)
Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    // User Management
    Route::resource('users', \App\Http\Controllers\Admin\UserManagementController::class);

    // User Customer Assignment Management (user-centric)
    Route::get('users/{user}/customers', [\App\Http\Controllers\UserCustomerController::class, 'userCustomers'])->name('users.customers');
    Route::post('users/{user}/customers', [\App\Http\Controllers\UserCustomerController::class, 'storeUserCustomers'])->name('users.customers.store');
    Route::delete('users/{user}/customers/{userCustomer}', [\App\Http\Controllers\UserCustomerController::class, 'destroyUserCustomer'])->name('users.customers.destroy');

    // Role Management (to be implemented)
    Route::resource('roles', \App\Http\Controllers\Admin\RoleManagementController::class);

    // Permission Management (to be implemented)
    Route::resource('permissions', \App\Http\Controllers\Admin\PermissionManagementController::class);

    // Territory Management
    Route::resource('territories', \App\Http\Controllers\Admin\TerritoryManagementController::class);

    // Announcement Management
    Route::resource('announcements', \App\Http\Controllers\Admin\AnnouncementController::class);

    // Period Management
    Route::resource('periods', \App\Http\Controllers\Admin\PeriodManagementController::class);

    // E-Invoice Request Management (KBS/admin only - checked in controller)
    Route::get('e-invoice-requests', [\App\Http\Controllers\Admin\EInvoiceRequestController::class, 'index'])->name('e-invoice-requests.index');
    Route::get('e-invoice-requests/{id}/edit', [\App\Http\Controllers\Admin\EInvoiceRequestController::class, 'edit'])->name('e-invoice-requests.edit');
    Route::put('e-invoice-requests/{id}', [\App\Http\Controllers\Admin\EInvoiceRequestController::class, 'update'])->name('e-invoice-requests.update');

    // Invoices Management (KBS/admin only - checked in controller)
    Route::get('invoices', [\App\Http\Controllers\Admin\InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('invoices/{id}', [\App\Http\Controllers\Admin\InvoiceController::class, 'show'])->name('invoices.show');

    // Invoice Resync Management (KBS/admin only - checked in controller)
    Route::get('invoice/resync', [\App\Http\Controllers\Admin\InvoiceController::class, 'resync'])->name('invoice.resync');
    Route::post('invoice/resync', [\App\Http\Controllers\Admin\InvoiceController::class, 'updateModificationDate'])->name('invoice.resync.update');

    // Report routes (KBS/admin only - checked in controller)
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\ReportController::class, 'index'])->name('index');
        Route::get('/sales', [\App\Http\Controllers\Admin\ReportController::class, 'salesReport'])->name('sales');
        Route::get('/transactions', [\App\Http\Controllers\Admin\ReportController::class, 'transactionReport'])->name('transactions');
        Route::get('/transactions/{id}/detail', [\App\Http\Controllers\Admin\ReportController::class, 'getOrderDetail'])->name('transactions.detail');
        Route::get('/customers', [\App\Http\Controllers\Admin\ReportController::class, 'customerReport'])->name('customers');
        Route::post('/customers/update-modification-date', [\App\Http\Controllers\Admin\ReportController::class, 'updateCustomerModificationDate'])->name('customers.update-modification-date');
        Route::get('/receipts', [\App\Http\Controllers\Admin\ReportController::class, 'receiptReport'])->name('receipts');
        Route::get('/customer-balance', [\App\Http\Controllers\Admin\ReportController::class, 'customerBalanceReport'])->name('customer-balance');
        Route::get('/customer-balance/{customerId}/detail', [\App\Http\Controllers\Admin\ReportController::class, 'getCustomerBalanceDetail'])->name('customer-balance.detail');
    });
});


Route::get('/create', function(){
    AccArCust::create([
        'CUSTNO' => '3000/TEST'.rand(1,1000),
        'ADD1' => 'New Address 1'.  rand(1, 1000),
        'ADD2' => 'New Address 2'.  rand(1, 1000),
    ]);
    dd(1);
});

Route::get('/update', function(){
    AccArCust::find('3000/S01')
    ->fill([
        'ADD1' => 'New Address 1'.  rand(1, 1000),
        'ADD2' => 'New Address 2'.  rand(1, 1000),
    ])
    ->save();
});


Route::get('/delete', function(){
    AccArCust::find('3000/TEST292')->delete();
});

// Test route: Check stock transactions for an item
Route::get('/test/check-stock/{itemno}', function($itemno){
    $item = Icitem::find($itemno);
    if (!$item) {
        return response()->json(['error' => 'Item not found'], 404);
    }

    // Get all transactions
    $transactions = ItemTransaction::where('ITEMNO', $itemno)
        ->orderBy('CREATED_ON', 'desc')
        ->get();

    // Calculate stock from transactions
    $calculatedStock = ItemTransaction::where('ITEMNO', $itemno)->sum('quantity');

    return response()->json([
        'item' => [
            'ITEMNO' => $item->ITEMNO,
            'DESP' => $item->DESP,
            'QTY' => $item->QTY,
        ],
        'calculated_stock_from_transactions' => $calculatedStock ?? 0,
        'transaction_count' => $transactions->count(),
        'transactions' => $transactions->map(function($t) {
            return [
                'id' => $t->id,
                'transaction_type' => $t->transaction_type,
                'quantity' => $t->quantity,
                'stock_before' => $t->stock_before,
                'stock_after' => $t->stock_after,
                'reference_type' => $t->reference_type,
                'reference_id' => $t->reference_id,
                'notes' => $t->notes,
                'created_on' => $t->CREATED_ON,
            ];
        }),
    ]);
});

// Test route: Add 10 quantity to all icitem
Route::get('/test/add-stock-all', function(){
    DB::beginTransaction();
    try {
        $items = Icitem::all();
        $count = 0;
        $quantity = 10;

        foreach ($items as $item) {
            // Calculate current stock from transactions
            $stockBefore = ItemTransaction::where('ITEMNO', $item->ITEMNO)->sum('quantity');
            if ($stockBefore === null) {
                $stockBefore = $item->QTY ?? 0;
            }

            // Calculate new stock
            $stockAfter = $stockBefore + $quantity;

            // Create transaction record
            ItemTransaction::create([
                'ITEMNO' => $item->ITEMNO,
                'transaction_type' => 'in',
                'quantity' => $quantity,
                'reference_type' => 'test',
                'reference_id' => 'bulk-add-test',
                'notes' => 'Bulk test: Added 10 quantity to all items',
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'CREATED_BY' => auth()->user()->id ?? null,
                'UPDATED_BY' => auth()->user()->id ?? null,
                'CREATED_ON' => now(),
                'UPDATED_ON' => now(),
            ]);

            // Update icitem QTY field
            $item->QTY = $stockAfter;
            $item->UPDATED_BY = auth()->user()->id ?? null;
            $item->UPDATED_ON = now();
            $item->save();

            $count++;
        }

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => "Successfully added {$quantity} quantity to {$count} items",
            'items_updated' => $count,
            'quantity_added' => $quantity
        ]);
    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ], 500);
    }
});



Route::get('/maintenance/recalc-orders', function () {
    $recalculated = 0;
    $errors = [];

    // Stream through to avoid loading everything at once
    \App\Models\Order::with('items')->chunk(200, function ($orders) use (&$recalculated, &$errors) {
        foreach ($orders as $order) {
            try {
                $order->calculate(); // uses updated gross/net logic (trade returns minus, discounts included)
                $order->save();
                $recalculated++;
            } catch (\Throwable $e) {
                $errors[] = [
                    'reference_no' => $order->reference_no,
                    'error' => $e->getMessage(),
                ];
            }
        }
    });

    return response()->json([
        'status' => 'ok',
        'recalculated' => $recalculated,
        'errors' => $errors,
    ]);
});
