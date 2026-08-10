<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AppConfigController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $minimum = (string) config('app.min_supported_version', '1.0.0');
        $latest = (string) config('app.version', '1.0.0');
        $client = $this->clientVersion($request->header('X-Client'));

        return response()->json([
            'min_supported_version' => $minimum,
            'latest_version' => $latest,
            'maintenance' => (bool) config('app.maintenance_mode', false),
            'message' => config('app.maintenance_message'),
            'force_update' => $client !== null && version_compare($client, $minimum, '<'),
        ]);
    }

    private function clientVersion(?string $client): ?string
    {
        if ($client === null || ! str_contains($client, '/')) {
            return null;
        }

        $version = trim((string) str()->afterLast($client, '/'));

        return preg_match('/^\d+(?:\.\d+){0,2}$/', $version) === 1 ? $version : null;
    }
}
