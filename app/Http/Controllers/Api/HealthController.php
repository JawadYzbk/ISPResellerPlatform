<?php

namespace App\Http\Controllers\Api;

use App\Actions\CheckApplicationHealth;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class HealthController extends Controller
{
    public function show(CheckApplicationHealth $health): JsonResponse
    {
        $result = $health->handle();

        return response()->json($result, $result['status'] === 'ok' ? 200 : 503);
    }
}
