<?php

namespace App\Http\Controllers\Web;

use App\Actions\CreateUpstreamLink;
use App\Actions\GetPopDetails;
use App\Actions\ListPops;
use App\Actions\SavePop;
use App\Http\Controllers\Controller;
use App\Models\Pop;
use App\Models\Router;
use App\Models\Tenant;
use App\Models\UpstreamLink;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class PopOperationsController extends Controller
{
    public function index(Request $request, ListPops $listPops): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('network.view'), 403);
        $pops = $listPops->handle($request->string('status')->toString() ?: null, $request->string('search')->toString() ?: null);
        $rows = $pops->getCollection()->map(fn (Pop $pop): array => [
            'id' => $pop->id,
            'name' => $pop->name,
            'code' => $pop->code,
            'address' => $pop->address,
            'status' => $pop->status,
            'routers_count' => $pop->routers_count,
            'upstream_links_count' => $pop->upstream_links_count,
        ])->values();
        $pops = new LengthAwarePaginator($rows, $pops->total(), $pops->perPage(), $pops->currentPage(), ['path' => $request->url(), 'query' => $request->query()]);

        return Inertia::render('Operations/Pops', [
            'pops' => $pops,
            'filters' => $request->only(['status', 'search']),
            'canManage' => $user->can('network.provision'),
            'statuses' => ['active', 'maintenance', 'down', 'decommissioned'],
        ]);
    }

    public function show(Request $request, Pop $pop, GetPopDetails $getDetails): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('network.view'), 403);
        $pop = $getDetails->handle($pop);

        return Inertia::render('Operations/PopShow', [
            'pop' => [
                'id' => $pop->id,
                'name' => $pop->name,
                'code' => $pop->code,
                'address' => $pop->address,
                'status' => $pop->status,
                'routers' => $pop->routers->map(fn (Router $router): array => ['public_id' => $router->public_id, 'name' => $router->name, 'host' => $router->host, 'status' => $router->status])->values(),
                'upstream_links' => $pop->upstreamLinks->map(fn (UpstreamLink $link): array => ['id' => $link->id, 'provider_name' => $link->provider_name, 'capacity_mbps' => $link->capacity_mbps, 'monthly_cost_amount' => $link->monthly_cost_amount, 'currency' => $link->currency, 'contract_start' => $link->contract_start?->toDateString(), 'contract_end' => $link->contract_end?->toDateString(), 'notes' => $link->notes])->values(),
            ],
            'canManage' => $user->can('network.provision'),
            'statuses' => ['active', 'maintenance', 'down', 'decommissioned'],
        ]);
    }

    public function store(Request $request, SavePop $save): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('network.provision') && $user->tenant instanceof Tenant, 403);
        $validated = $request->validate($this->popRules($user->tenant));
        $pop = $save->handle($validated);

        return redirect()->route('operations.pops.show', $pop)->with('success', "POP {$pop->name} created.");
    }

    public function update(Request $request, Pop $pop, SavePop $save): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('network.provision') && $user->tenant instanceof Tenant, 403);
        $validated = $request->validate($this->popRules($user->tenant, $pop));
        $save->handle($validated, $pop);

        return redirect()->route('operations.pops.show', $pop)->with('success', "POP {$pop->name} updated.");
    }

    public function storeUpstreamLink(Request $request, Pop $pop, CreateUpstreamLink $create): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('network.provision'), 403);
        $validated = $request->validate([
            'provider_name' => ['required', 'string', 'max:255'],
            'capacity_mbps' => ['nullable', 'integer', 'min:0'],
            'monthly_cost_amount' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'contract_start' => ['required', 'date'],
            'contract_end' => ['nullable', 'date', 'after_or_equal:contract_start'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $create->handle($pop, $validated);

        return redirect()->route('operations.pops.show', $pop)->with('success', "Upstream link for {$pop->name} recorded.");
    }

    /** @return array<string, array<int, mixed>> */
    private function popRules(Tenant $tenant, ?Pop $pop = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:32', Rule::unique('pops', 'code')->where('tenant_id', $tenant->id)->ignore($pop?->id)],
            'address' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'maintenance', 'down', 'decommissioned'])],
        ];
    }
}
