<?php

namespace App\Http\Controllers\Web;

use App\Models\Tenant;
use Inertia\Inertia;
use Inertia\Response;

final class PortalPageController
{
    public function signIn(Tenant $tenant): Response
    {
        return Inertia::render('Portal/SignIn', ['tenant' => $this->tenant($tenant)]);
    }

    public function dashboard(Tenant $tenant): Response
    {
        return Inertia::render('Portal/Dashboard', ['tenant' => $this->tenant($tenant)]);
    }

    /** @return array{slug: string, name: string, logo_url: string|null} */
    private function tenant(Tenant $tenant): array
    {
        return [
            'slug' => $tenant->slug,
            'name' => $tenant->name,
            'logo_url' => $tenant->logoUrl(),
        ];
    }
}
