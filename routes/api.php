<?php

use App\Http\Controllers\Api\ApiTokenController;
use App\Http\Controllers\Api\CustomerApiController;
use App\Http\Controllers\Api\PaymentApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/tokens', [ApiTokenController::class, 'store'])->middleware('throttle:login')->name('api.tokens.store');

    Route::middleware(['auth:sanctum', 'tenant'])->group(function (): void {
        Route::delete('/tokens/current', [ApiTokenController::class, 'destroy'])->name('api.tokens.destroy');
        Route::get('/me', fn (Request $request) => $request->user())->name('api.me');
        Route::get('/customers', [CustomerApiController::class, 'index'])->name('api.customers.index');
        Route::get('/customers/{customer:public_id}', [CustomerApiController::class, 'show'])->name('api.customers.show');
        Route::post('/payments', [PaymentApiController::class, 'store'])->middleware('idempotency')->name('api.payments.store');
    });
});
