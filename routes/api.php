<?php

use App\Http\Controllers\OtpController;
use App\Http\Controllers\Product\CategoryController;
use App\Http\Controllers\Product\ProductController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

// Public routes
Route::post('register', [AuthController::class, 'register']);
Route::post('login',    [AuthController::class, 'login']);
Route::post('forgotpass',      [OtpController::class, 'otpSender']);
Route::post('verify',      [OtpController::class, 'verifyOtp']);
Route::post('resetpass',      [OtpController::class, 'resetPassword']);

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

















});
