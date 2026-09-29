<?php

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('login', function () {
    return new JsonResponse([
        'success' => false,
        'message' => 'Unauthenticated',
    ], 401);
})->name('login');
