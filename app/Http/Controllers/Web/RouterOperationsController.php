<?php

namespace App\Http\Controllers\Web;

use App\Actions\CheckRouterHealth;
use App\Actions\CreateRouter;
use App\Actions\ListRouters;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateRouterRequest;
use App\Models\Pop;
use App\Models\Router;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

final class RouterOperationsController extends Controller
{
    public function index(Request $request, ListRouters $listRouters): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('network.view'), 403);
        $routers = $listRouters->handle($request->string('status')->toString() ?: null);
        $rows = $routers->getCollection()->map(function (mixed $router): array {
            if (! $router instanceof Router) {
                throw new LogicException('Router paginator contained an invalid record.');
            }

            return [
                'public_id' => $router->public_id,
                'name' => $router->name,
                'host' => $router->host,
                'api_port' => $router->api_port,
                'status' => $router->status,
                'tls_verify' => $router->tls_verify,
                'last_seen_at' => $router->last_seen_at?->toIso8601String(),
                'consecutive_failures' => (int) ($router->metadata['consecutive_failures'] ?? 0),
                'services_count' => $router->services_count,
                'pop' => $router->pop === null ? null : ['name' => $router->pop->name, 'code' => $router->pop->code],
            ];
        })->values();
        $routers = new LengthAwarePaginator(
            $rows,
            $routers->total(),
            $routers->perPage(),
            $routers->currentPage(),
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return Inertia::render('Operations/Routers', [
            'routers' => $routers,
            'filters' => $request->only(['status']),
            'canCheckHealth' => $user->can('network.view'),
            'canCreate' => $user->can('network.provision'),
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()?->can('network.provision') === true, 403);

        return Inertia::render('Operations/RouterCreate', [
            'pops' => Pop::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    public function store(CreateRouterRequest $request, CreateRouter $createRouter): RedirectResponse
    {
        abort_unless($request->user()?->can('network.provision') === true, 403);
        $user = $request->user();
        abort_unless($user instanceof User && $user->tenant instanceof Tenant, 403);
        $router = $createRouter->handle($request->validated(), $user->tenant);

        return redirect()->route('operations.routers')->with('success', "Router {$router->name} registered.");
    }

    public function health(Request $request, Router $router, CheckRouterHealth $check): RedirectResponse
    {
        abort_unless($request->user()?->can('network.view') === true, 403);
        $incident = $check->handle($router, 1);

        return redirect()->route('operations.routers')->with(
            $incident === null ? 'success' : 'error',
            $incident === null ? "Router {$router->name} is reachable." : "Router {$router->name} is offline.",
        );
    }
}
