<?php

use App\Http\Controllers\Api\ApiTokenController;
use App\Http\Controllers\Api\AppConfigController;
use App\Http\Controllers\Api\BalanceImportController;
use App\Http\Controllers\Api\CollectorPaymentController;
use App\Http\Controllers\Api\CollectorSyncController;
use App\Http\Controllers\Api\CustomerApiController;
use App\Http\Controllers\Api\CustomerImportController;
use App\Http\Controllers\Api\EquipmentImportController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\NetworkCommandController;
use App\Http\Controllers\Api\PartnerApiController;
use App\Http\Controllers\Api\PaymentApiController;
use App\Http\Controllers\Api\PlanImportController;
use App\Http\Controllers\Api\PortalAuthController;
use App\Http\Controllers\Api\PortalBillingController;
use App\Http\Controllers\Api\PortalController;
use App\Http\Controllers\Api\ServiceApiController;
use App\Http\Controllers\Api\ServiceImportController;
use App\Http\Controllers\Api\TechnicianMediaController;
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
        Route::get('/billing', [PortalBillingController::class, 'show'])->middleware('portal.auth')->name('api.portal.billing');
    });

    Route::middleware(['auth:sanctum', 'tenant'])->group(function (): void {
        Route::delete('/tokens/current', [ApiTokenController::class, 'destroy'])->name('api.tokens.destroy');
        Route::get('/app/config', [AppConfigController::class, 'show'])->name('api.app.config');
        Route::get('/me', fn (Request $request) => $request->user())->name('api.me');
        Route::middleware('any-abilities:staff:operator,staff:collector,staff:technician')->group(function (): void {
            Route::get('/customers', [CustomerApiController::class, 'index'])->name('api.customers.index');
            Route::get('/customers/{customer:public_id}', [CustomerApiController::class, 'show'])->name('api.customers.show');
            Route::get('/services', [ServiceApiController::class, 'index'])->name('api.services.index');
            Route::get('/services/{service}', [ServiceApiController::class, 'show'])->name('api.services.show');
            Route::post('/payments', [PaymentApiController::class, 'store'])->middleware('idempotency')->name('api.payments.store');
        });
        Route::middleware('abilities:staff:operator')->group(function (): void {
            Route::post('/services/{service}/activate', [ServiceApiController::class, 'activate'])->middleware('idempotency')->name('api.services.activate');
            Route::post('/services/{service}/suspend', [ServiceApiController::class, 'suspend'])->middleware('idempotency')->name('api.services.suspend');
            Route::post('/services/{service}/resume', [ServiceApiController::class, 'resume'])->middleware('idempotency')->name('api.services.resume');
            Route::post('/imports/customers', [CustomerImportController::class, 'store'])->name('api.imports.customers.store');
            Route::post('/imports/plans', [PlanImportController::class, 'store'])->name('api.imports.plans.store');
            Route::post('/imports/plans/{import}/rollback', [PlanImportController::class, 'rollback'])->name('api.imports.plans.rollback');
            Route::post('/imports/services', [ServiceImportController::class, 'store'])->name('api.imports.services.store');
            Route::post('/imports/services/{import}/rollback', [ServiceImportController::class, 'rollback'])->name('api.imports.services.rollback');
            Route::post('/imports/equipment', [EquipmentImportController::class, 'store'])->name('api.imports.equipment.store');
            Route::post('/imports/equipment/{import}/rollback', [EquipmentImportController::class, 'rollback'])->name('api.imports.equipment.rollback');
            Route::post('/imports/balances', [BalanceImportController::class, 'store'])->name('api.imports.balances.store');
            Route::post('/imports/balances/{import}/rollback', [BalanceImportController::class, 'rollback'])->name('api.imports.balances.rollback');
            Route::post('/imports/{import}/rollback', [CustomerImportController::class, 'rollback'])->name('api.imports.rollback');
            Route::get('/partners', [PartnerApiController::class, 'index'])->name('api.partners.index');
            Route::get('/partners/{partner}', [PartnerApiController::class, 'show'])->name('api.partners.show');
            Route::post('/partners/{partner}/wallet-top-ups', [PartnerApiController::class, 'topUp'])->middleware('idempotency')->name('api.partners.wallet-top-ups');
            Route::post('/network/commands/{command}/retry', [NetworkCommandController::class, 'retry'])->middleware('idempotency')->name('api.network.commands.retry');
        });
        Route::middleware('abilities:staff:collector')->group(function (): void {
            Route::post('/collector/payments/batch', [CollectorPaymentController::class, 'store'])->middleware('idempotency')->name('api.collector.payments.batch');
            Route::get('/collector/sync/bootstrap', [CollectorSyncController::class, 'bootstrap'])->name('api.collector.sync.bootstrap');
            Route::get('/collector/sync/delta', [CollectorSyncController::class, 'delta'])->name('api.collector.sync.delta');
            Route::post('/collector/sync/push', [CollectorSyncController::class, 'push'])->middleware('idempotency')->name('api.collector.sync.push');
        });
        Route::middleware('abilities:staff:technician')->group(function (): void {
            Route::post('/technician/uploads', [TechnicianMediaController::class, 'store'])->name('api.technician.uploads.store');
            Route::get('/technician/services/{service}/diagnostics', [TechnicianWorkOrderController::class, 'diagnostics'])->name('api.technician.services.diagnostics');
            Route::get('/technician/inventory', [TechnicianWorkOrderController::class, 'inventory'])->name('api.technician.inventory');
            Route::get('/technician/work-orders', [TechnicianWorkOrderController::class, 'index'])->name('api.technician.work-orders.index');
            Route::get('/technician/work-orders/{workOrder}', [TechnicianWorkOrderController::class, 'show'])->name('api.technician.work-orders.show');
            Route::post('/technician/work-orders/{workOrder}/complete', [TechnicianWorkOrderController::class, 'complete'])->middleware('idempotency')->name('api.technician.work-orders.complete');
        });
    });
});
