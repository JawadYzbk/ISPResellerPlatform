<?php

namespace App\Http\Controllers\Api;

use App\Actions\ListCustomersApi;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Support\Api\CustomerApiResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CustomerApiController extends Controller
{
    public function index(Request $request, ListCustomersApi $listCustomers): JsonResponse
    {
        $this->authorize('viewAny', Customer::class);

        return response()->json($listCustomers->handle($request, min($request->integer('per_page', 20), 100)));
    }

    public function show(string $publicId, CustomerApiResource $resource): JsonResponse
    {
        $customer = Customer::query()->where('public_id', $publicId)->firstOrFail();
        $this->authorize('view', $customer);

        return response()->json($resource->make($customer));
    }
}
