<?php

use App\Http\Controllers\Api\ApiTokenController;
use App\Http\Controllers\Api\CollectorPaymentController;
use App\Http\Controllers\Api\CustomerApiController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\NetworkCommandController;
use App\Http\Controllers\Api\PaymentApiController;
use App\Http\Controllers\Api\PortalAuthController;
use App\Http\Controllers\Api\PortalController;
use App\Http\Controllers\Api\TechnicianWorkOrderController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', [HealthController::class, 'show'])->name('api.health');
    Route::post('/tokens', [ApiTokenController::class, 'store'])->middleware('throttle:login')->name('api.tokens.store');
    Route::prefix('portal/{tenant:slug}')->middleware('portal.tenant')->group(function (): void {
        Route::post('/otp/request', [PortalAuthController::class, 'requestOtp'])->middleware('throttle:login')->name('api.portal.otp.request');
        Route::post('/otp/verify', [PortalAuthController::class, 'verifyOtp'])->middleware('throttle:login')->name('api.portal.otp.verify');
        Route::get('/me', [PortalController::class, 'me'])->middleware('portal.auth')->name('api.portal.me');
    });

    Route::middleware(['auth:sanctum', 'tenant'])->group(function (): void {
        Route::delete('/tokens/current', [ApiTokenController::class, 'destroy'])->name('api.tokens.destroy');
        Route::get('/me', fn (Request $request) => $request->user())->name('api.me');
        Route::get('/customers', [CustomerApiController::class, 'index'])->name('api.customers.index');
        Route::get('/customers/{customer:public_id}', [CustomerApiController::class, 'show'])->name('api.customers.show');
        Route::post('/payments', [PaymentApiController::class, 'store'])->middleware('idempotency')->name('api.payments.store');
        Route::post('/collector/payments/batch', [CollectorPaymentController::class, 'store'])->middleware('idempotency')->name('api.collector.payments.batch');
        Route::post('/network/commands/{command}/retry', [NetworkCommandController::class, 'retry'])->middleware('idempotency')->name('api.network.commands.retry');
        Route::get('/technician/work-orders', [TechnicianWorkOrderController::class, 'index'])->name('api.technician.work-orders.index');
        Route::get('/technician/work-orders/{workOrder}', [TechnicianWorkOrderController::class, 'show'])->name('api.technician.work-orders.show');
        Route::post('/technician/work-orders/{workOrder}/complete', [TechnicianWorkOrderController::class, 'complete'])->middleware('idempotency')->name('api.technician.work-orders.complete');
    });
});
