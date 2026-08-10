<?php

namespace App\Http\Controllers\Web;

use App\Models\Tenant;
use Inertia\Inertia;
use Inertia\Response;

final class PortalPageController
{
    public function signIn(Tenant $tenant): Response
    {
        return Inertia::render('Portal/SignIn', ['tenant' => ['slug' => $tenant->slug, 'name' => $tenant->name]]);
    }

    public function dashboard(Tenant $tenant): Response
    {
        return Inertia::render('Portal/Dashboard', ['tenant' => ['slug' => $tenant->slug, 'name' => $tenant->name]]);
    }
}
