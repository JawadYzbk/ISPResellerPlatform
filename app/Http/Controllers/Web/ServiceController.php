<?php

namespace App\Http\Controllers\Web;

use App\Actions\CreateService;
use App\Actions\EnqueueNetworkCommand;
use App\Actions\ListServices;
use App\Actions\TransitionService;
use App\Enums\ServiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateServiceRequest;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Router;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ServiceController extends Controller
{
    public function create(Customer $customer): Response
    {
        $this->authorize('create', Service::class);

        return Inertia::render('Services/Create', [
            'customer' => $customer->only(['public_id', 'code', 'first_name', 'last_name']),
            'plans' => Plan::query()->where('status', 'active')->orderBy('name')->get(['id', 'public_id', 'name', 'download_kbps', 'upload_kbps', 'duration_days', 'amount_minor', 'currency']),
            'routers' => Router::query()->orderBy('name')->get(['id', 'public_id', 'name']),
        ]);
    }

    public function store(CreateServiceRequest $request, Customer $customer, CreateService $createService): RedirectResponse
    {
        $this->authorize('create', Service::class);

        $service = $createService->handle($customer, $request->validated(), $request->user());

        return redirect()->route('customers.show', $customer->public_id)->with('success', "Service {$service->username} created and awaiting activation.");
    }

    public function activate(Request $request, Service $service, TransitionService $transition, EnqueueNetworkCommand $enqueue): RedirectResponse
    {
        $this->authorize('activate', $service);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $updated = $transition->handle($service, ServiceStatus::Active, $user, ['reason' => 'manual_operator']);
        $enqueue->handle($updated, 'activate', ['reason' => 'manual_operator']);

        return $this->redirectToCustomer($service, 'Service activation queued.');
    }

    public function suspend(Request $request, Service $service, TransitionService $transition, EnqueueNetworkCommand $enqueue): RedirectResponse
    {
        $this->authorize('suspend', $service);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:64'], 'note' => ['nullable', 'string', 'max:5000']]);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $updated = $transition->handle($service, ServiceStatus::Suspended, $user, $validated);
        $enqueue->handle($updated, 'suspend', $validated);

        return $this->redirectToCustomer($service, 'Service suspension queued.');
    }

    public function resume(Request $request, Service $service, TransitionService $transition, EnqueueNetworkCommand $enqueue): RedirectResponse
    {
        $this->authorize('activate', $service);
        if ($service->suspension_reason !== 'auto_overdue') {
            abort_unless($request->user()?->can('services.force_resume') === true, 403);
        }
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $updated = $transition->handle($service, ServiceStatus::Active, $user, ['reason' => 'manual_resume']);
        $enqueue->handle($updated, 'activate', ['reason' => 'manual_resume']);

        return $this->redirectToCustomer($service, 'Service reactivation queued.');
    }

    public function terminate(Request $request, Service $service, TransitionService $transition, EnqueueNetworkCommand $enqueue): RedirectResponse
    {
        $this->authorize('terminate', $service);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:5000']]);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $updated = $transition->handle($service, ServiceStatus::Terminated, $user, $validated);
        $enqueue->handle($updated, 'disconnect', ['reason' => 'service_terminated']);

        return $this->redirectToCustomer($service, 'Service terminated and network disconnect queued.');
    }

    public function resync(Request $request, Service $service, EnqueueNetworkCommand $enqueue): RedirectResponse
    {
        $action = $service->status === ServiceStatus::Suspended ? 'suspend' : 'activate';
        $this->authorize($action === 'suspend' ? 'suspend' : 'activate', $service);
        abort_if($service->status === ServiceStatus::Terminated, 422, 'Terminated services cannot be re-synced.');

        $enqueue->handle($service, $action, ['reason' => 'manual_resync']);

        return redirect()->route('customers.show', $service->customer->public_id)->with('success', "Service {$service->username} queued for network re-sync.");
    }

    private function redirectToCustomer(Service $service, string $message): RedirectResponse
    {
        return redirect()->route('customers.show', $service->customer->public_id)->with('success', $message);
    }

    public function index(Request $request, ListServices $listServices): Response
    {
        $this->authorize('viewAny', Service::class);

        return Inertia::render('Services/Index', [
            'services' => $listServices->handle($request->string('search')->toString() ?: null, $request->string('status')->toString() ?: null),
            'filters' => $request->only(['search', 'status']),
        ]);
    }
}
