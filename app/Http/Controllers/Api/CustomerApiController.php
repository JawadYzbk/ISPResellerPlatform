<?php

namespace App\Http\Controllers\Api;

use App\Actions\ListCustomers;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CustomerApiController extends Controller
{
    public function index(Request $request, ListCustomers $listCustomers): JsonResponse
    {
        $this->authorize('viewAny', Customer::class);

        return response()->json($listCustomers->handle($request->string('search')->toString() ?: null, $request->string('status')->toString() ?: null, min($request->integer('per_page', 20), 100)));
    }

    public function show(string $publicId): JsonResponse
    {
        $customer = Customer::query()->where('public_id', $publicId)->firstOrFail();
        $this->authorize('view', $customer);

        return response()->json($customer->load(['zone', 'services.plan']));
    }
}
