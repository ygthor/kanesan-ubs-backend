<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DeveloperController;
use App\Http\Controllers\Api\SyncController;
// Correctly namespacing the CustomerController we've been working on
use App\Http\Controllers\Api\CustomerController; // <<< Ensure this is the controller from laravel_customer_controller_v2
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\GlStatementController;

use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ReceiptController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DebtController;
use App\Http\Controllers\Api\IcitemController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\OrderItemController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\InvoiceItemController;
// Removed: use App\Models\User; // Not directly used for routing definitions
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::any('/test/response', function () {
    return response()->json([
        'status' => 200,
        'message' => 'ok',
        'data' => [
            'name' => 'test'
        ]
    ]);
})->name('test.response');

Route::post('/sync/local', [SyncController::class, 'syncLocalData']); // Recommended array syntax
Route::post('/auth/token', [AuthController::class, 'get_token'])->name('auth.token'); // Recommended array syntax

Route::get('/script_to_run/update_customer_name',function(){
    DB::statement("
        UPDATE customers
        SET company_name = name
        WHERE company_name IS NULL OR company_name = '';
    ");
    echo 'success';
});



/**
 * @unauthenticated
 * @group Testing
 *
 * Just a test endpoint
 *
 * @response 201 {
 * "message": "Tested",
 * "data": {
 * "message": "ok",
 * }
 * }
 * @response 400 {
 * "message": "Validation errors",
 * "errors": {
 * "name": ["just error"]
 * }
 * }
 */
Route::post('/test/api', function () {
    return response()->json([
        'status' => 200,
        'message' => 'ok',
        'data' => [
            'name' => 'test'
        ]
    ]);
})->name('test.api');
Route::get('products', [ProductController::class,'index']);
Route::get('icitem', [IcitemController::class,'index']);
// Route::middleware([])->group(function () {
Route::middleware(['auth:sanctum'])->group(function () {

    Route::any('/me', [UserController::class, 'info'])->name('user.info'); // Recommended array syntax
    // Route::get('/user', [UserController::class, 'info'])->name('user.info'); // This is redundant if /me is any HTTP verb

    Route::get('/dashboard', [DashboardController::class, 'getSummary'])->name('dashboard.summary');

    // CRUD API routes for the Customer model
    // This will create the following routes:
    // GET      /api/customers             -> customers.index   (CustomerController@index)
    // POST     /api/customers             -> customers.store   (CustomerController@store)
    // GET      /api/customers/{customer}  -> customers.show    (CustomerController@show)
    // PUT/PATCH /api/customers/{customer} -> customers.update  (CustomerController@update)
    // DELETE   /api/customers/{customer}  -> customers.destroy (CustomerController@destroy)
    Route::apiResource('customers', CustomerController::class);
    
    Route::delete('/orders/{id}', [OrderController::class, 'deleteOrder']);
    Route::delete('/order-items/{id}', [OrderController::class, 'deleteOrderItem']);

    Route::apiResource('gl-statement', GlStatementController::class)->only(['index', 'store', 'update', 'show']);

    Route::apiResource('orders', OrderController::class)->only(['index', 'store', 'update', 'show']);
    Route::apiResource('orders-items', OrderItemController::class)->only(['index', 'store', 'update', 'show','destroy']);
    Route::apiResource('receipts', ReceiptController::class);

    Route::post('invoices/batch-print', [InvoiceController::class, 'batchPrint']);
    Route::apiResource('invoices', InvoiceController::class)->only(['index', 'store', 'update', 'show']);
    Route::apiResource('invoices-items', InvoiceItemController::class)->only(['index', 'store', 'update', 'show','destroy']);

    Route::get('/debts', [DebtController::class, 'index'])->name('debts.index');
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');

    // The route below for '/customer' (singular) pointing to index would be redundant
    // if you use apiResource with 'customers' (plural) as the resource name.
    // If you specifically need GET /api/customer to list all customers, you can add it separately:
    // Route::get('/customer', [CustomerController::class, 'index'])->name('customer.legacy.index');

    // Commented out routes from your original file if they are handled by apiResource or are for a different controller
    // Route::get('/customer/{id}', CustomerController::class . '@view')->name('customer.view'); // Handled by customers.show
    // Route::post('/ubs/customer', CustomerController::class . '@store')->name('customer.store'); // This might be for a different CustomerController (UBS)

    // Other routes from your example file
    // Route::get('/orders', SomeOtherController::class . '@view')->name('customer.view');
    // Route::get('/cash_bill', SomeOtherController::class . '@view')->name('customer.view');
    // Route::get('/credit_note', SomeOtherController::class . '@view')->name('customer.view');
    // Route::get('/statement', SomeOtherController::class . '@view')->name('customer.view');
    // Route::get('/invoice', SomeOtherController::class . '@view')->name('invoice.view');
    // Route::post('/invoice', SomeOtherController::class . '@view')->name('invoice.add');
});


Route::post('developer/create', [DeveloperController::class, 'create'])->name('developer.create');


Route::post('data_sync/',[SyncController::class, 'fetchTableData']);
