<?php

namespace App\Http\Controllers\Api;

use App\Actions\RetryNetworkCommand;
use App\Http\Controllers\Controller;
use App\Models\NetworkCommand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class NetworkCommandController extends Controller
{
    public function retry(Request $request, int $command, RetryNetworkCommand $retry): JsonResponse
    {
        abort_unless($request->user()?->can('network.provision'), 403);
        $newCommand = $retry->handle(NetworkCommand::query()->findOrFail($command));

        return response()->json(['id' => $newCommand->id, 'status' => $newCommand->status, 'desired_state_version' => $newCommand->desired_state_version], 202);
    }
}
