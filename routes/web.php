<?php

use App\Models\UBS\AccArCust;
use Illuminate\Support\Facades\Route;

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

// Demo route for testing
Route::get('/demo-users', function() {
    return view('demo-users');
})->name('demo-users')->middleware('auth');

// Admin routes (require authentication and permissions)
Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    // User Management
    Route::resource('users', \App\Http\Controllers\Admin\UserManagementController::class);
    
    // Role Management (to be implemented)
    Route::resource('roles', \App\Http\Controllers\Admin\RoleManagementController::class);
    
    // Permission Management (to be implemented)
    Route::resource('permissions', \App\Http\Controllers\Admin\PermissionManagementController::class);
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