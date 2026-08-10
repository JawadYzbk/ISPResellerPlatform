<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\ReauthenticateController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\Web\BillingController;
use App\Http\Controllers\Web\CredentialOperationsController;
use App\Http\Controllers\Web\CustomerController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\InventoryOperationsController;
use App\Http\Controllers\Web\InvitationController;
use App\Http\Controllers\Web\NetworkOperationsController;
use App\Http\Controllers\Web\PartnerController;
use App\Http\Controllers\Web\PlanOperationsController;
use App\Http\Controllers\Web\PortalPageController;
use App\Http\Controllers\Web\ReportController;
use App\Http\Controllers\Web\RouterOperationsController;
use App\Http\Controllers\Web\SecurityController;
use App\Http\Controllers\Web\ServiceController;
use App\Http\Controllers\Web\SettingsController;
use App\Http\Controllers\Web\TicketOperationsController;
use App\Http\Controllers\Web\UserOperationsController;
use App\Http\Controllers\Web\WorkOrderOperationsController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'));

Route::prefix('portal/{tenant:slug}')->group(function (): void {
    Route::get('/', [PortalPageController::class, 'signIn'])->name('portal.sign-in');
    Route::get('/dashboard', [PortalPageController::class, 'dashboard'])->name('portal.dashboard');
});

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');
    Route::get('/invite/{token}', [InvitationController::class, 'show'])->name('invitations.show');
    Route::post('/invite/{token}', [InvitationController::class, 'accept'])->name('invitations.accept');
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
    Route::get('/settings/general', [SettingsController::class, 'general'])->name('settings.general');
    Route::put('/settings/general', [SettingsController::class, 'updateGeneral'])->middleware('recent-auth')->name('settings.general.update');
    Route::get('/settings/users', [UserOperationsController::class, 'index'])->name('settings.users');
    Route::post('/settings/users/invite', [UserOperationsController::class, 'invite'])->middleware('recent-auth')->name('settings.users.invite');
    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('/customers/create', [CustomerController::class, 'create'])->name('customers.create');
    Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
    Route::get('/customers/{customer:public_id}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
    Route::put('/customers/{customer:public_id}', [CustomerController::class, 'update'])->name('customers.update');
    Route::get('/customers/{customer:public_id}/payments/create', [CustomerController::class, 'createPayment'])->name('customers.payments.create');
    Route::post('/customers/{customer:public_id}/payments', [CustomerController::class, 'storePayment'])->name('customers.payments.store');
    Route::get('/customers/{customer:public_id}', [CustomerController::class, 'show'])->name('customers.show');
    Route::post('/customers/{customer:public_id}/anonymize', [CustomerController::class, 'anonymize'])->middleware('recent-auth')->name('customers.anonymize');
    Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
    Route::post('/services/{service:public_id}/activate', [ServiceController::class, 'activate'])->name('services.activate');
    Route::post('/services/{service:public_id}/suspend', [ServiceController::class, 'suspend'])->name('services.suspend');
    Route::post('/services/{service:public_id}/resume', [ServiceController::class, 'resume'])->name('services.resume');
    Route::post('/services/{service:public_id}/resync', [ServiceController::class, 'resync'])->name('services.resync');
    Route::get('/operations/network-commands', [NetworkOperationsController::class, 'index'])->name('operations.network-commands');
    Route::post('/operations/network-commands/{command:public_id}/retry', [NetworkOperationsController::class, 'retry'])->name('operations.network-commands.retry');
    Route::get('/operations/routers', [RouterOperationsController::class, 'index'])->name('operations.routers');
    Route::get('/operations/routers/create', [RouterOperationsController::class, 'create'])->name('operations.routers.create');
    Route::post('/operations/routers', [RouterOperationsController::class, 'store'])->middleware('recent-auth')->name('operations.routers.store');
    Route::post('/operations/routers/{router:public_id}/health', [RouterOperationsController::class, 'health'])->name('operations.routers.health');
    Route::get('/billing/invoices', [BillingController::class, 'invoices'])->name('billing.invoices');
    Route::post('/billing/invoices/{invoice:public_id}/issue', [BillingController::class, 'issue'])->name('billing.invoices.issue');
    Route::get('/billing/payments', [BillingController::class, 'payments'])->name('billing.payments');
    Route::post('/billing/payments/{payment:public_id}/reverse', [BillingController::class, 'reversePayment'])->middleware('recent-auth')->name('billing.payments.reverse');
    Route::get('/operations/tickets', [TicketOperationsController::class, 'index'])->name('operations.tickets');
    Route::get('/operations/tickets/{ticket:public_id}', [TicketOperationsController::class, 'show'])->name('operations.tickets.show');
    Route::post('/operations/tickets/{ticket:public_id}/status', [TicketOperationsController::class, 'status'])->name('operations.tickets.status');
    Route::post('/operations/tickets/{ticket:public_id}/messages', [TicketOperationsController::class, 'reply'])->name('operations.tickets.messages');
    Route::get('/operations/work-orders', [WorkOrderOperationsController::class, 'index'])->name('operations.work-orders');
    Route::post('/operations/work-orders/{workOrder:public_id}/complete', [WorkOrderOperationsController::class, 'complete'])->name('operations.work-orders.complete');
    Route::get('/operations/inventory', [InventoryOperationsController::class, 'index'])->name('operations.inventory');
    Route::post('/operations/inventory/{unit}/assign', [InventoryOperationsController::class, 'assign'])->middleware('recent-auth')->name('operations.inventory.assign');
    Route::get('/operations/credentials', [CredentialOperationsController::class, 'index'])->name('operations.credentials');
    Route::post('/operations/credentials/{credential}/assign', [CredentialOperationsController::class, 'assign'])->middleware('recent-auth')->name('operations.credentials.assign');
    Route::get('/plans', [PlanOperationsController::class, 'index'])->name('plans.index');
    Route::get('/plans/create', [PlanOperationsController::class, 'create'])->name('plans.create');
    Route::post('/plans', [PlanOperationsController::class, 'store'])->name('plans.store');
    Route::get('/customers/{customer:public_id}/services/create', [ServiceController::class, 'create'])->name('services.create');
    Route::post('/customers/{customer:public_id}/services', [ServiceController::class, 'store'])->name('services.store');
    Route::get('/partners/commercial', [PartnerController::class, 'commercial'])->name('partners.commercial');
    Route::get('/reports/finance', [ReportController::class, 'finance'])->name('reports.finance');
    Route::get('/reports/operations', [ReportController::class, 'operations'])->name('reports.operations');
});
