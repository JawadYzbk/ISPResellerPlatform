<?php

namespace App\Http\Controllers\Web;

use App\Actions\ListServices;
use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ServiceController extends Controller
{
    public function index(Request $request, ListServices $listServices): Response
    {
        $this->authorize('viewAny', Service::class);

        return Inertia::render('Services/Index', [
            'services' => $listServices->handle($request->string('search')->toString() ?: null, $request->string('status')->toString() ?: null),
            'filters' => $request->only(['search', 'status']),
        ]);
    }
}
