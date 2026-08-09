<?php

namespace App\Http\Controllers\Web;

use App\Actions\GetCustomerDetails;
use App\Actions\ListCustomers;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class CustomerController extends Controller
{
    public function index(Request $request, ListCustomers $listCustomers): Response
    {
        return Inertia::render('Customers/Index', [
            'customers' => $listCustomers->handle($request->string('search')->toString() ?: null, $request->string('status')->toString() ?: null),
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function show(Customer $customer, GetCustomerDetails $getCustomerDetails): Response
    {
        return Inertia::render('Customers/Show', ['customer' => $getCustomerDetails->handle($customer)]);
    }
}
