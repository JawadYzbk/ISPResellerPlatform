<?php

namespace App\Http\Controllers\Api;

use App\Actions\RetryNetworkCommand;
use App\Http\Controllers\Controller;
use App\Models\NetworkCommand;
use App\Support\Api\NetworkCommandApiResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class NetworkCommandController extends Controller
{
    public function show(Request $request, string $command, NetworkCommandApiResource $resource): JsonResponse
    {
        abort_unless($request->user()?->can('network.view'), 403);
        $networkCommand = NetworkCommand::query()->where('public_id', $command)->firstOrFail();

        return response()->json($resource->make($networkCommand));
    }

    public function retry(Request $request, string $command, RetryNetworkCommand $retry): JsonResponse
    {
        abort_unless($request->user()?->can('network.provision'), 403);
        $newCommand = $retry->handle(NetworkCommand::query()->where('public_id', $command)->firstOrFail());

        return response()->json(['id' => $newCommand->public_id, 'status' => $newCommand->status, 'desired_state_version' => $newCommand->desired_state_version], 202);
    }
}
