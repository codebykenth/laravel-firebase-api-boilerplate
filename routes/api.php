<?php

use App\Http\Controllers\Api\ProductController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::apiResource('products', ProductController::class)
    ->only(['index', 'store', 'show', 'destroy']);

Route::post('/products/{product}/update', [ProductController::class, 'update']);;