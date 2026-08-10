<?php

namespace App\Http\Controllers\Web;

use App\Actions\GetPopDetails;
use App\Actions\ListPops;
use App\Http\Controllers\Controller;
use App\Models\Pop;
use App\Models\Router;
use App\Models\UpstreamLink;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
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

        return Inertia::render('Operations/Pops', ['pops' => $pops, 'filters' => $request->only(['status', 'search'])]);
    }

    public function show(Request $request, Pop $pop, GetPopDetails $getDetails): Response
    {
        abort_unless($request->user()?->can('network.view') === true, 403);
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
        ]);
    }
}
