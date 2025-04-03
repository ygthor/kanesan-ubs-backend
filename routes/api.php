<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DeveloperController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\UserController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::any('/test/response', function(){
    return response()->json([
        'status' => 200,
        'message' => 'ok',
        'data' => [
            'name' => 'test'
        ]
    ]);
})->name('test.response');

Route::post('/sync/local', SyncController::class . '@syncLocalData');
Route::post('/auth/token', AuthController::class . '@get_token')->name('auth.token');



Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user',UserController::class . '@info')->name('user.info');
});


Route::post('developer/create', [DeveloperController::class,'create'])->name('developer.create');