<?php

use App\Models\UBS\AccArCust;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
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