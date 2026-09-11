<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\ReimbursementController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::post('/reimbursements/analyze', [ReimbursementController::class, 'store']);
    Route::get('/reimbursements', [ReimbursementController::class, 'index']);
    Route::post('/reimbursements/analyze', [ReimbursementController::class, 'store']);
    Route::patch('/reimbursements/{id}/status', [ReimbursementController::class, 'updateStatus']);

    Route::get('/leaves', [LeaveController::class, 'index']);
    Route::post('/leaves', [LeaveController::class, 'store']);
    Route::patch('/leaves/{id}/status', [LeaveController::class, 'updateStatus']);
    Route::patch('/leaves/{id}/cancel', [LeaveController::class, 'cancel']);
});
