<?php

use App\Http\Controllers\Api\Admin\AdController as AdminAdController;
use App\Http\Controllers\Api\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\AdController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

// --- Public, read-only (matches the old FastAPI /api/* exactly) ---
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);
Route::get('/ads', [AdController::class, 'index']);

// Sanctum's own guard requires this route to exist under 'auth:sanctum'
// even though login/logout live in web.php.
Route::middleware('auth:sanctum')->get('/user', [AuthController::class, 'me']);

// --- Protected admin endpoints ---
Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
    Route::get('/categories', [AdminCategoryController::class, 'index']);
    Route::post('/categories', [AdminCategoryController::class, 'store']);

    Route::post('/products', [AdminProductController::class, 'store']);
    Route::post('/products/{id}', [AdminProductController::class, 'update']); // Laravel + multipart -> POST, not PUT
    Route::delete('/products/{id}', [AdminProductController::class, 'destroy']);
    Route::delete('/products/{productId}/images/{imageId}', [AdminProductController::class, 'destroyImage']);

    Route::post('/ads', [AdminAdController::class, 'store']);
    Route::delete('/ads/{id}', [AdminAdController::class, 'destroy']);

    Route::get('/users', [AdminUserController::class, 'index']);
    Route::post('/users', [AdminUserController::class, 'store']);
    Route::post('/users/{id}', [AdminUserController::class, 'update']);
    Route::delete('/users/{id}', [AdminUserController::class, 'destroy']);
});