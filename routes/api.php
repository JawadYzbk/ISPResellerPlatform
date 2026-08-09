<?php

use App\Http\Controllers\Api\ApiTokenController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/tokens', [ApiTokenController::class, 'store'])->middleware('throttle:login')->name('api.tokens.store');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::delete('/tokens/current', [ApiTokenController::class, 'destroy'])->name('api.tokens.destroy');
        Route::get('/me', fn (Request $request) => $request->user())->name('api.me');
    });
});
