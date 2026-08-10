<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\ReauthenticateController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\Web\CustomerController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\PartnerController;
use App\Http\Controllers\Web\PortalPageController;
use App\Http\Controllers\Web\ReportController;
use App\Http\Controllers\Web\SecurityController;
use App\Http\Controllers\Web\ServiceController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'));

Route::prefix('portal/{tenant:slug}')->group(function (): void {
    Route::get('/', [PortalPageController::class, 'signIn'])->name('portal.sign-in');
    Route::get('/dashboard', [PortalPageController::class, 'dashboard'])->name('portal.dashboard');
});

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');
});

Route::middleware(['auth', 'tenant'])->group(function (): void {
    Route::post('/logout', LogoutController::class)->name('logout');
    Route::get('/two-factor/setup', [TwoFactorController::class, 'setup'])->name('two-factor.setup');
    Route::post('/two-factor/setup', [TwoFactorController::class, 'confirm'])->middleware('recent-auth')->name('two-factor.setup.confirm');
    Route::get('/two-factor/challenge', [TwoFactorController::class, 'challenge'])->name('two-factor.challenge');
    Route::post('/two-factor/challenge', [TwoFactorController::class, 'verify'])->name('two-factor.challenge.verify');
    Route::get('/security/reauthenticate', [ReauthenticateController::class, 'create'])->name('security.reauthenticate');
    Route::post('/security/reauthenticate', [ReauthenticateController::class, 'store'])->name('security.reauthenticate.store');
    Route::get('/security/sessions', [SecurityController::class, 'sessions'])->middleware('2fa')->name('security.sessions');
    Route::delete('/security/sessions/{session}', [SecurityController::class, 'revoke'])->middleware('2fa')->name('security.sessions.revoke');
    Route::post('/settings/locale', [SecurityController::class, 'locale'])->middleware('2fa')->name('settings.locale');
});

Route::middleware(['auth', 'tenant', '2fa'])->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('/customers/create', [CustomerController::class, 'create'])->name('customers.create');
    Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
    Route::get('/customers/{customer:public_id}', [CustomerController::class, 'show'])->name('customers.show');
    Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
    Route::get('/partners/commercial', [PartnerController::class, 'commercial'])->name('partners.commercial');
    Route::get('/reports/finance', [ReportController::class, 'finance'])->name('reports.finance');
    Route::get('/reports/operations', [ReportController::class, 'operations'])->name('reports.operations');
});
