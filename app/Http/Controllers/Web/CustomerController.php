<?php

namespace App\Http\Controllers\Web;

use App\Actions\CreateCustomer;
use App\Actions\GetCustomerDetails;
use App\Actions\ListCustomers;
use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerRequest;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\Zone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class CustomerController extends Controller
{
    public function create(Request $request): Response
    {
        $this->authorize('create', Customer::class);
        $user = $request->user();
        abort_unless($user?->tenant instanceof Tenant, 403);

        return Inertia::render('Customers/Create', ['zones' => Zone::query()->orderBy('name')->get(['id', 'name', 'code'])]);
    }

    public function store(CustomerRequest $request, CreateCustomer $createCustomer): RedirectResponse
    {
        $this->authorize('create', Customer::class);
        $user = $request->user();
        abort_unless($user?->tenant instanceof Tenant, 403);
        $customer = $createCustomer->handle($request, $user->tenant);

        return redirect()->route('customers.show', $customer->public_id)->with('success', 'Customer created.');
    }

    public function index(Request $request, ListCustomers $listCustomers): Response
    {
        $this->authorize('viewAny', Customer::class);

        return Inertia::render('Customers/Index', [
            'customers' => $listCustomers->handle($request->string('search')->toString() ?: null, $request->string('status')->toString() ?: null),
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function show(Customer $customer, GetCustomerDetails $getCustomerDetails): Response
    {
        $this->authorize('view', $customer);

        return Inertia::render('Customers/Show', ['customer' => $getCustomerDetails->handle($customer)]);
    }
}
