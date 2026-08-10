<?php

namespace App\Http\Controllers\Web;

use App\Actions\CreateIpAddress;
use App\Actions\CreateIpPool;
use App\Actions\ListIpPools;
use App\Http\Controllers\Controller;
use App\Models\IpAddress;
use App\Models\IpPool;
use App\Models\Router;
use App\Models\Tenant;
use App\Models\User;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

final class IpPoolOperationsController extends Controller
{
    public function index(Request $request, ListIpPools $listPools): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('network.view'), 403);
        $pools = $listPools->handle();
        $selected = $request->integer('pool_id') > 0
            ? IpPool::query()->findOrFail($request->integer('pool_id'))
            : $pools->first();
        $addresses = $selected?->addresses()->with('service')->orderBy('address')->paginate(50)->withQueryString();
        $addressRows = $addresses === null ? null : new LengthAwarePaginator(
            $addresses->getCollection()->map(function (mixed $address): array {
                if (! $address instanceof IpAddress) {
                    throw new \LogicException('IP address paginator contained an invalid record.');
                }

                return [
                    'id' => $address->id,
                    'address' => $address->address,
                    'status' => $address->status,
                    'assigned_at' => $address->assigned_at?->toIso8601String(),
                    'service' => $address->service === null ? null : ['public_id' => $address->service->public_id, 'username' => $address->service->username],
                ];
            })->values(),
            $addresses->total(),
            $addresses->perPage(),
            $addresses->currentPage(),
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return Inertia::render('Operations/IpPools', [
            'pools' => $pools->map(fn (IpPool $pool): array => [
                'id' => $pool->id,
                'name' => $pool->name,
                'cidr' => $pool->cidr,
                'gateway' => $pool->gateway,
                'type' => $pool->type,
                'version' => $pool->version,
                'is_active' => $pool->is_active,
                'addresses_count' => $pool->addresses_count,
                'free_addresses_count' => $pool->free_addresses_count,
                'router' => $pool->router === null ? null : ['id' => $pool->router->id, 'name' => $pool->router->name],
            ])->values(),
            'selectedPoolId' => $selected?->id,
            'addresses' => $addressRows,
            'routers' => $user->can('network.provision') ? Router::query()->orderBy('name')->get(['id', 'name', 'host'])->values() : [],
            'canManage' => $user->can('network.provision'),
        ]);
    }

    public function storePool(Request $request, CreateIpPool $create): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('network.provision') && $user->tenant instanceof Tenant, 403);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'cidr' => ['required', 'string', 'max:64'],
            'gateway' => ['nullable', 'ip'],
            'type' => ['required', Rule::in(['dynamic', 'static', 'blocked'])],
            'version' => ['required', 'integer', Rule::in([4, 6])],
            'router_id' => [Rule::excludeIf(fn (): bool => blank($request->input('router_id'))), Rule::exists('routers', 'id')->where('tenant_id', $user->tenant->id)],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        try {
            $pool = $create->handle($validated);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['cidr' => $exception->getMessage()]);
        }

        return redirect()->route('operations.ip-pools', ['pool_id' => $pool->id])->with('success', "IP pool {$pool->name} created.");
    }

    public function storeAddress(Request $request, IpPool $pool, CreateIpAddress $create): RedirectResponse
    {
        abort_unless($request->user()?->can('network.provision') === true, 403);
        $validated = $request->validate(['address' => ['required', 'ip'], 'status' => ['required', Rule::in(['free', 'reserved', 'conflict'])]]);
        try {
            $create->handle($pool, $validated);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['address' => $exception->getMessage()]);
        }

        return redirect()->route('operations.ip-pools', ['pool_id' => $pool->id])->with('success', "Address {$validated['address']} recorded.");
    }
}
