<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TokenController;

/*
|--------------------------------------------------------------------------
| SPA Authentication Routes (Session-based)
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function () {
    // Public routes
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);

    // Protected routes (session-based)
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'user']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::put('/password', [AuthController::class, 'changePassword']);
    });
});

/*
|--------------------------------------------------------------------------
| API Token Authentication Routes
|--------------------------------------------------------------------------
*/
Route::prefix('token')->group(function () {
    // Public routes
    Route::post('/create', [TokenController::class, 'createToken']);
    Route::post('/register', [TokenController::class, 'register']);

    // Protected routes (token-based)
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/user', [TokenController::class, 'user']);
        Route::delete('/revoke', [TokenController::class, 'revokeToken']);
        Route::delete('/revoke-all', [TokenController::class, 'revokeAllTokens']);
        Route::delete('/revoke/{tokenId}', [TokenController::class, 'revokeSpecificToken']);
        Route::get('/list', [TokenController::class, 'getTokens']);
    });
});

/*
|--------------------------------------------------------------------------
| Example Protected API Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    // This route works with both session and token authentication
    Route::get('/profile', function (Request $request) {
        return response()->json([
            'user' => $request->user(),
            'auth_type' => $request->user()->currentAccessToken() ? 'token' : 'session'
        ]);
    });

    // Example resource routes
    Route::apiResource('posts', 'PostController');
    Route::apiResource('categories', 'CategoryController');
});
