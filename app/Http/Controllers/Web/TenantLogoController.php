<?php

namespace App\Http\Controllers\Web;

use App\Models\Tenant;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

final class TenantLogoController
{
    public function __invoke(Tenant $tenant): Response
    {
        abort_unless(is_string($tenant->logo_path) && $tenant->logo_path !== '', 404);

        $disk = Storage::disk('public');
        abort_unless($disk->exists($tenant->logo_path), 404);

        return $disk->response($tenant->logo_path, $tenant->slug.'.logo', [
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
