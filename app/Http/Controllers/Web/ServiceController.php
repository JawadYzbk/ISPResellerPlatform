<?php

namespace App\Http\Controllers\Web;

use App\Actions\CreateService;
use App\Actions\EnqueueNetworkCommand;
use App\Actions\ListServices;
use App\Enums\ServiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateServiceRequest;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Router;
use App\Models\Service;
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

    public function resync(Request $request, Service $service, EnqueueNetworkCommand $enqueue): RedirectResponse
    {
        $action = $service->status === ServiceStatus::Suspended ? 'suspend' : 'activate';
        $this->authorize($action === 'suspend' ? 'suspend' : 'activate', $service);
        abort_if($service->status === ServiceStatus::Terminated, 422, 'Terminated services cannot be re-synced.');

        $enqueue->handle($service, $action, ['reason' => 'manual_resync']);

        return redirect()->route('customers.show', $service->customer->public_id)->with('success', "Service {$service->username} queued for network re-sync.");
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
