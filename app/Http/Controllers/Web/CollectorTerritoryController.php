<?php

namespace App\Http\Controllers\Web;

use App\Actions\UpdateCollectorTerritories;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCollectorTerritoryRequest;
use App\Models\User;
use App\Models\Zone;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class CollectorTerritoryController extends Controller
{
    public function index(Request $request): Response
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->can('users.manage'), 403);

        $collectors = User::query()
            ->where('role', 'collector')
            ->with(['activeCollectorZoneAssignments:id,user_id,zone_id'])
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'collector_all_zones'])
            ->map(fn (User $collector): array => [
                'id' => $collector->id,
                'name' => $collector->name,
                'email' => $collector->email,
                'all_zones' => (bool) $collector->collector_all_zones,
                'zone_ids' => $collector->activeCollectorZoneAssignments->pluck('zone_id')->map(fn (mixed $id): int => (int) $id)->values()->all(),
            ])->values();

        $zones = Zone::query()
            ->with('parent:id,name')
            ->orderBy('name')
            ->get(['id', 'parent_id', 'name', 'code'])
            ->map(fn (Zone $zone): array => [
                'id' => $zone->id,
                'parent_id' => $zone->parent_id,
                'parent_name' => $zone->parent?->name,
                'name' => $zone->name,
                'code' => $zone->code,
            ])->values();

        return Inertia::render('Settings/CollectorTerritories', [
            'collectors' => $collectors,
            'zones' => $zones,
        ]);
    }

    public function update(
        UpdateCollectorTerritoryRequest $request,
        User $collector,
        UpdateCollectorTerritories $update,
    ): RedirectResponse {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        try {
            $update->handle($actor, $collector, $request->validated());
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['zone_ids' => $exception->getMessage()]);
        }

        return redirect()->route('settings.collector-territories')->with('success', "{$collector->name}'s territory was updated.");
    }
}
