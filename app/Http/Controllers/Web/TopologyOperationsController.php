<?php

namespace App\Http\Controllers\Web;

use App\Actions\AssignServiceTopology;
use App\Actions\ClearServiceTopology;
use App\Actions\SaveDistributionBox;
use App\Actions\SaveNetworkBuilding;
use App\Enums\ServiceStatus;
use App\Http\Controllers\Controller;
use App\Models\DistributionBox;
use App\Models\NetworkBuilding;
use App\Models\Pop;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class TopologyOperationsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->authorizedUser($request, 'network.view');
        $buildings = NetworkBuilding::query()
            ->withCount('distributionBoxes')
            ->withCount(['services as active_services_count' => fn ($query) => $query->where('status', '<>', ServiceStatus::Terminated->value)])
            ->orderBy('name')
            ->get()
            ->map(fn (NetworkBuilding $building): array => [
                'public_id' => $building->public_id,
                'name' => $building->name,
                'code' => $building->code,
                'address' => $building->address,
                'latitude' => $building->latitude,
                'longitude' => $building->longitude,
                'floors' => $building->floors,
                'unit_count' => $building->unit_count,
                'status' => $building->status,
                'distribution_boxes_count' => $building->distribution_boxes_count,
                'active_services_count' => $building->active_services_count,
            ])
            ->values();

        return Inertia::render('Operations/TopologyBuildings', [
            'buildings' => $buildings,
            'canManage' => $user->can('network.provision'),
            'statuses' => $this->buildingStatuses(),
        ]);
    }

    public function show(Request $request, NetworkBuilding $networkBuilding): Response
    {
        $user = $this->authorizedUser($request, 'network.view');
        $networkBuilding->load([
            'distributionBoxes' => fn ($query) => $query
                ->with('pop:id,name,code')
                ->withCount(['services as assigned_services_count' => fn ($services) => $services->where('status', '<>', ServiceStatus::Terminated->value)])
                ->with(['services' => fn ($services) => $services->where('status', '<>', ServiceStatus::Terminated->value)->with(['customer:id,public_id,first_name,last_name,code', 'plan:id,name'])->orderBy('network_port')]),
        ]);

        $services = Service::query()
            ->where('status', '<>', ServiceStatus::Terminated->value)
            ->with(['customer:id,public_id,first_name,last_name,code', 'plan:id,name', 'distributionBox:public_id,id,code'])
            ->orderBy('username')
            ->get()
            ->map(fn (Service $service): array => $this->servicePayload($service))
            ->values();

        return Inertia::render('Operations/TopologyBuildingShow', [
            'building' => $this->buildingPayload($networkBuilding),
            'services' => $services,
            'pops' => Pop::query()->where('tenant_id', $networkBuilding->tenant_id)->orderBy('name')->get(['id', 'name', 'code'])->values(),
            'canManage' => $user->can('network.provision'),
            'buildingStatuses' => $this->buildingStatuses(),
            'boxTypes' => ['distribution', 'cabinet', 'splitter'],
            'boxStatuses' => ['active', 'maintenance', 'full', 'retired'],
        ]);
    }

    public function storeBuilding(Request $request, SaveNetworkBuilding $save): RedirectResponse
    {
        $user = $this->authorizedUser($request, 'network.provision');
        $tenant = $this->tenant($user);
        $validated = $request->validate($this->buildingRules($tenant));
        $building = $save->handle($tenant, $validated);

        return redirect()->route('operations.topology.buildings.show', $building)->with('success', "Building {$building->name} created.");
    }

    public function updateBuilding(Request $request, NetworkBuilding $networkBuilding, SaveNetworkBuilding $save): RedirectResponse
    {
        $user = $this->authorizedUser($request, 'network.provision');
        $tenant = $this->tenant($user);
        $validated = $request->validate($this->buildingRules($tenant, $networkBuilding));
        $save->handle($tenant, $validated, $networkBuilding);

        return redirect()->route('operations.topology.buildings.show', $networkBuilding)->with('success', "Building {$networkBuilding->name} updated.");
    }

    public function storeBox(Request $request, NetworkBuilding $networkBuilding, SaveDistributionBox $save): RedirectResponse
    {
        $user = $this->authorizedUser($request, 'network.provision');
        $this->assertTenant($user, $networkBuilding->tenant_id);
        $validated = $request->validate($this->boxRules($networkBuilding->tenant));
        $box = $save->handle($networkBuilding, $validated);

        return redirect()->route('operations.topology.buildings.show', $networkBuilding)->with('success', "Distribution box {$box->name} created.");
    }

    public function updateBox(Request $request, DistributionBox $distributionBox, SaveDistributionBox $save): RedirectResponse
    {
        $user = $this->authorizedUser($request, 'network.provision');
        $building = $distributionBox->building;
        $this->assertTenant($user, $building->tenant_id);
        $validated = $request->validate($this->boxRules($building->tenant, $distributionBox));
        $save->handle($building, $validated, $distributionBox);

        return redirect()->route('operations.topology.buildings.show', $building)->with('success', "Distribution box {$distributionBox->name} updated.");
    }

    public function assignService(Request $request, Service $service, AssignServiceTopology $assign): RedirectResponse
    {
        $user = $this->authorizedUser($request, 'network.provision');
        $this->assertTenant($user, $service->tenant_id);
        $validated = $request->validate([
            'distribution_box_id' => ['required', 'string'],
            'network_port' => ['required', 'integer', 'min:1'],
        ]);
        $box = DistributionBox::query()->where('public_id', $validated['distribution_box_id'])->firstOrFail();

        try {
            $assign->handle($service, $box, (int) $validated['network_port'], $user);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['topology' => $exception->getMessage()]);
        }

        return redirect()->route('operations.topology.buildings.show', $box->building)->with('success', "{$service->username} assigned to {$box->code} port {$validated['network_port']}.");
    }

    public function unassignService(Request $request, Service $service, ClearServiceTopology $clear): RedirectResponse
    {
        $user = $this->authorizedUser($request, 'network.provision');
        $this->assertTenant($user, $service->tenant_id);
        $building = $service->networkBuilding;

        try {
            $clear->handle($service, $user);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['topology' => $exception->getMessage()]);
        }

        abort_unless($building instanceof NetworkBuilding, 404);

        return redirect()->route('operations.topology.buildings.show', $building)->with('success', "{$service->username} unassigned from the network box.");
    }

    /** @return array<string, mixed> */
    private function buildingPayload(NetworkBuilding $building): array
    {
        return [
            'public_id' => $building->public_id,
            'name' => $building->name,
            'code' => $building->code,
            'address' => $building->address,
            'latitude' => $building->latitude,
            'longitude' => $building->longitude,
            'floors' => $building->floors,
            'unit_count' => $building->unit_count,
            'status' => $building->status,
            'notes' => $building->notes,
            'boxes' => $building->distributionBoxes->map(fn (DistributionBox $box): array => [
                'public_id' => $box->public_id,
                'name' => $box->name,
                'code' => $box->code,
                'box_type' => $box->box_type,
                'capacity_ports' => $box->capacity_ports,
                'used_ports' => $box->usedPorts(),
                'latitude' => $box->latitude,
                'longitude' => $box->longitude,
                'status' => $box->status,
                'notes' => $box->notes,
                'pop' => $box->pop ? ['id' => $box->pop->id, 'name' => $box->pop->name, 'code' => $box->pop->code] : null,
                'services' => $box->services->map(fn (Service $service): array => $this->servicePayload($service))->values(),
            ])->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function servicePayload(Service $service): array
    {
        return [
            'public_id' => $service->public_id,
            'username' => $service->username,
            'status' => $service->status->value,
            'customer' => $service->customer ? ['public_id' => $service->customer->public_id, 'name' => $service->customer->full_name, 'code' => $service->customer->code] : null,
            'plan' => $service->plan ? ['name' => $service->plan->name] : null,
            'distribution_box' => $service->distributionBox ? ['public_id' => $service->distributionBox->public_id, 'code' => $service->distributionBox->code] : null,
            'network_port' => $service->network_port,
        ];
    }

    /** @return array<string, array<int, mixed>> */
    private function buildingRules(Tenant $tenant, ?NetworkBuilding $building = null): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9][A-Za-z0-9_-]*$/', Rule::unique('network_buildings', 'code')->where('tenant_id', $tenant->id)->ignore($building?->id)],
            'address' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'floors' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'unit_count' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', Rule::in($this->buildingStatuses())],
            'notes' => ['nullable', 'string', 'max:4000'],
        ];
    }

    /** @return array<string, array<int, mixed>> */
    private function boxRules(Tenant $tenant, ?DistributionBox $box = null): array
    {
        return [
            'pop_id' => ['nullable', Rule::exists('pops', 'id')->where('tenant_id', $tenant->id)],
            'name' => ['required', 'string', 'max:160'],
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9][A-Za-z0-9_-]*$/', Rule::unique('distribution_boxes', 'code')->where('tenant_id', $tenant->id)->ignore($box?->id)],
            'box_type' => ['required', Rule::in(['distribution', 'cabinet', 'splitter'])],
            'capacity_ports' => ['required', 'integer', 'min:1', 'max:65535'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'status' => ['required', Rule::in(['active', 'maintenance', 'full', 'retired'])],
            'notes' => ['nullable', 'string', 'max:4000'],
        ];
    }

    /** @return list<string> */
    private function buildingStatuses(): array
    {
        return ['active', 'maintenance', 'retired'];
    }

    private function authorizedUser(Request $request, string $capability): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can($capability), 403);

        return $user;
    }

    private function tenant(User $user): Tenant
    {
        abort_unless($user->tenant instanceof Tenant, 403);

        return $user->tenant;
    }

    private function assertTenant(User $user, int $tenantId): void
    {
        abort_unless($user->tenant_id === $tenantId, 404);
    }
}
