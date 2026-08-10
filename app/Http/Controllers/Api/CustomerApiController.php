<?php

namespace App\Http\Controllers\Api;

use App\Actions\CreateCustomer;
use App\Actions\ListCustomersApi;
use App\Actions\UpdateCustomer;
use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerRequest;
use App\Http\Requests\CustomerUpdateRequest;
use App\Models\Customer;
use App\Models\Tenant;
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

    public function store(CustomerRequest $request, CreateCustomer $createCustomer, CustomerApiResource $resource): JsonResponse
    {
        $this->authorize('create', Customer::class);
        $user = $request->user();
        abort_unless($user?->tenant instanceof Tenant, 403);
        abort_unless(! $request->boolean('create_service') || $user->can('services.create'), 403);

        $customer = $createCustomer->handle($request, $user->tenant, $user);

        return response()->json($resource->make($customer), 201);
    }

    public function update(CustomerUpdateRequest $request, Customer $customer, UpdateCustomer $updateCustomer, CustomerApiResource $resource): JsonResponse
    {
        $this->authorize('update', $customer);

        return response()->json($resource->make($updateCustomer->handle($customer, $request)));
    }

    public function show(string $publicId, CustomerApiResource $resource): JsonResponse
    {
        $customer = Customer::query()->where('public_id', $publicId)->firstOrFail();
        $this->authorize('view', $customer);

        return response()->json($resource->make($customer));
    }
}
