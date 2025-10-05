<?php

use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\OtpController;
use App\Http\Controllers\PreOrderController;
use App\Http\Controllers\Product\CategoryController;
use App\Http\Controllers\Product\ProductController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;

// Public routes
Route::post('register', [AuthController::class, 'register']);
Route::post('login',    [AuthController::class, 'login']);
Route::post('forgotpass',      [OtpController::class, 'otpSender']);
Route::post('verify',      [OtpController::class, 'verifyOtp']);
Route::post('resetpass',      [OtpController::class, 'resetPassword']);


//Profile
Route::get('profile/{id}',[ProfileController::class, 'profile']);
Route::put('profile/{id}',[ProfileController::class, 'update']);

// Protected routes
Route::middleware('auth:api')->group(function () {
    Route::get('auth/me',      [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::post('auth/refresh',[AuthController::class, 'refresh']);

//======================================================================
//============================Admin can handle==========================
//======================================================================
Route::apiResource('categories',CategoryController::class);
Route::apiResource('products',ProductController::class);
Route::resource('orders', AdminOrderController::class)
    ->only(['index', 'show', 'update']);






//======================================================================
//============================Customer can handle=======================
//======================================================================

Route::apiResource('preorders', PreOrderController::class);
Route::apiResource('customer-orders', OrderController::class);






//======================================================================
//=========================Contact us===================================
//======================================================================

Route::get('contacts', [ContactController::class, 'index']);
Route::delete('contacts/{id}', [ContactController::class, 'destroy']);

});

//=============================================================
//====================public routes============================
//====================public routes============================
//=============================================================

Route::post('contact', [ContactController::class, 'store']);
Route::get('shop', [ProductController::class, 'index']);
Route::get('shop/{slug}', [ProductController::class, 'show']);
Route::apiResource('payments', PaymentController::class);
