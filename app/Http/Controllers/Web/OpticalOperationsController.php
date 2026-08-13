<?php

namespace App\Http\Controllers\Web;

use App\Actions\RecordOpticalReading;
use App\Actions\SaveOpticalDevice;
use App\Http\Controllers\Controller;
use App\Models\OpticalDevice;
use App\Models\OpticalReading;
use App\Models\Pop;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class OpticalOperationsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->authorizedUser($request, 'network.view');
        $devices = OpticalDevice::query()
            ->with(['pop:id,name,code', 'latestReading.service.customer'])
            ->withCount('readings')
            ->orderBy('name')
            ->get()
            ->map(fn (OpticalDevice $device): array => $this->devicePayload($device))
            ->values();

        return Inertia::render('Operations/Optical', [
            'devices' => $devices,
            'pops' => Pop::query()->orderBy('name')->get(['id', 'name', 'code'])->values(),
            'services' => Service::query()
                ->where('status', '<>', 'terminated')
                ->with('customer:id,public_id,first_name,last_name,code')
                ->orderBy('username')
                ->limit(500)
                ->get(['id', 'public_id', 'username', 'customer_id'])
                ->map(fn (Service $service): array => [
                    'public_id' => $service->public_id,
                    'username' => $service->username,
                    'customer' => $service->customer ? [
                        'name' => $service->customer->full_name,
                        'code' => $service->customer->code,
                    ] : null,
                ])
                ->values(),
            'canManage' => $user->can('network.provision'),
            'deviceTypes' => ['olt', 'onu', 'splitter'],
            'deviceStatuses' => ['active', 'maintenance', 'retired'],
        ]);
    }

    public function storeDevice(Request $request, SaveOpticalDevice $save): RedirectResponse
    {
        $user = $this->authorizedUser($request, 'network.provision');
        $tenant = $this->tenant($user);
        $data = $request->validate($this->deviceRules($tenant));
        $device = $save->handle($tenant, $data);

        return back()->with('success', "Optical device {$device->name} created.");
    }

    public function updateDevice(Request $request, OpticalDevice $opticalDevice, SaveOpticalDevice $save): RedirectResponse
    {
        $user = $this->authorizedUser($request, 'network.provision');
        $this->assertTenant($user, $opticalDevice->tenant_id);
        $tenant = $this->tenant($user);
        $data = $request->validate($this->deviceRules($tenant, $opticalDevice));
        $save->handle($tenant, $data, $opticalDevice);

        return back()->with('success', "Optical device {$opticalDevice->name} updated.");
    }

    public function recordReading(Request $request, RecordOpticalReading $record): RedirectResponse
    {
        $user = $this->authorizedUser($request, 'network.provision');
        $validated = $request->validate([
            'optical_device_id' => ['required', 'string', Rule::exists('optical_devices', 'public_id')->where('tenant_id', $user->tenant_id)],
            'service_id' => ['nullable', 'string', Rule::exists('services', 'public_id')->where('tenant_id', $user->tenant_id)],
            'work_order_id' => ['nullable', 'string', Rule::exists('work_orders', 'public_id')->where('tenant_id', $user->tenant_id)],
            'onu_serial' => ['nullable', 'string', 'max:120'],
            'rx_dbm' => ['nullable', 'numeric', 'between:-60,10'],
            'tx_dbm' => ['nullable', 'numeric', 'between:-20,20'],
            'temperature_c' => ['nullable', 'numeric', 'between:-50,150'],
            'recorded_at' => ['nullable', 'date'],
        ]);
        if (blank($validated['service_id'] ?? null) && blank($validated['work_order_id'] ?? null) && blank($validated['onu_serial'] ?? null)) {
            throw ValidationException::withMessages(['onu_serial' => 'Link the reading to a service, work order, or ONU serial.']);
        }
        if (blank($validated['rx_dbm'] ?? null) && blank($validated['tx_dbm'] ?? null) && blank($validated['temperature_c'] ?? null)) {
            throw ValidationException::withMessages(['rx_dbm' => 'Enter at least one optical measurement.']);
        }

        $device = OpticalDevice::query()->where('public_id', $validated['optical_device_id'])->firstOrFail();
        $service = filled($validated['service_id'] ?? null)
            ? Service::query()->where('public_id', $validated['service_id'])->firstOrFail()
            : null;
        $workOrder = filled($validated['work_order_id'] ?? null)
            ? WorkOrder::query()->where('public_id', $validated['work_order_id'])->firstOrFail()
            : null;

        try {
            $record->handle($user, $device, [
                'onu_serial' => $validated['onu_serial'] ?? null,
                'rx_dbm' => $validated['rx_dbm'] ?? null,
                'tx_dbm' => $validated['tx_dbm'] ?? null,
                'temperature_c' => $validated['temperature_c'] ?? null,
                'recorded_at' => $validated['recorded_at'] ?? now(),
            ], $service, $workOrder);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['optical_device_id' => $exception->getMessage()]);
        }

        return back()->with('success', 'Optical reading recorded.');
    }

    /** @return array<string, mixed> */
    private function devicePayload(OpticalDevice $device): array
    {
        $reading = $device->latestReading;

        return [
            'public_id' => $device->public_id,
            'name' => $device->name,
            'code' => $device->code,
            'device_type' => $device->device_type,
            'vendor' => $device->vendor,
            'model' => $device->model,
            'host' => $device->host,
            'management_port' => $device->management_port,
            'status' => $device->status,
            'notes' => $device->notes,
            'pop' => $device->pop ? ['name' => $device->pop->name, 'code' => $device->pop->code] : null,
            'readings_count' => $device->readings_count,
            'latest_reading' => $reading ? [
                'recorded_at' => $reading->recorded_at?->toIso8601String(),
                'onu_serial' => $reading->onu_serial,
                'rx_dbm' => $reading->rx_dbm,
                'tx_dbm' => $reading->tx_dbm,
                'temperature_c' => $reading->temperature_c,
                'service' => $reading->service ? [
                    'username' => $reading->service->username,
                    'customer' => $reading->service->customer?->full_name,
                ] : null,
            ] : null,
        ];
    }

    /** @return array<string, array<int, mixed>> */
    private function deviceRules(Tenant $tenant, ?OpticalDevice $device = null): array
    {
        return [
            'pop_id' => ['nullable', Rule::exists('pops', 'id')->where('tenant_id', $tenant->id)],
            'name' => ['required', 'string', 'max:160'],
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9][A-Za-z0-9_-]*$/', Rule::unique('optical_devices', 'code')->where('tenant_id', $tenant->id)->ignore($device?->id)],
            'device_type' => ['required', Rule::in(['olt', 'onu', 'splitter'])],
            'vendor' => ['nullable', 'string', 'max:80'],
            'model' => ['nullable', 'string', 'max:120'],
            'host' => ['nullable', 'string', 'max:255'],
            'management_port' => ['nullable', 'integer', 'between:1,65535'],
            'status' => ['required', Rule::in(['active', 'maintenance', 'retired'])],
            'notes' => ['nullable', 'string', 'max:4000'],
        ];
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
