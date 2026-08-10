<?php

use App\Http\Controllers\Api\ApiTokenController;
use App\Http\Controllers\Api\CollectorPaymentController;
use App\Http\Controllers\Api\CustomerApiController;
use App\Http\Controllers\Api\CustomerImportController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\NetworkCommandController;
use App\Http\Controllers\Api\PaymentApiController;
use App\Http\Controllers\Api\PortalAuthController;
use App\Http\Controllers\Api\PortalController;
use App\Http\Controllers\Api\ServiceApiController;
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
        Route::get('/services', [ServiceApiController::class, 'index'])->name('api.services.index');
        Route::get('/services/{service}', [ServiceApiController::class, 'show'])->name('api.services.show');
        Route::post('/services/{service}/activate', [ServiceApiController::class, 'activate'])->middleware('idempotency')->name('api.services.activate');
        Route::post('/services/{service}/suspend', [ServiceApiController::class, 'suspend'])->middleware('idempotency')->name('api.services.suspend');
        Route::post('/services/{service}/resume', [ServiceApiController::class, 'resume'])->middleware('idempotency')->name('api.services.resume');
        Route::post('/imports/customers', [CustomerImportController::class, 'store'])->name('api.imports.customers.store');
        Route::post('/imports/{import}/rollback', [CustomerImportController::class, 'rollback'])->name('api.imports.rollback');
        Route::post('/payments', [PaymentApiController::class, 'store'])->middleware('idempotency')->name('api.payments.store');
        Route::post('/collector/payments/batch', [CollectorPaymentController::class, 'store'])->middleware('idempotency')->name('api.collector.payments.batch');
        Route::post('/network/commands/{command}/retry', [NetworkCommandController::class, 'retry'])->middleware('idempotency')->name('api.network.commands.retry');
        Route::get('/technician/work-orders', [TechnicianWorkOrderController::class, 'index'])->name('api.technician.work-orders.index');
        Route::get('/technician/work-orders/{workOrder}', [TechnicianWorkOrderController::class, 'show'])->name('api.technician.work-orders.show');
        Route::post('/technician/work-orders/{workOrder}/complete', [TechnicianWorkOrderController::class, 'complete'])->middleware('idempotency')->name('api.technician.work-orders.complete');
    });
});
