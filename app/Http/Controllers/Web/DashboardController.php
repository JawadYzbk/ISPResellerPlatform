<?php

namespace App\Http\Controllers\Web;

use App\Actions\GetDashboardMetrics;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    public function __invoke(GetDashboardMetrics $getDashboardMetrics): Response
    {
        return Inertia::render('Dashboard/Index', ['metrics' => $getDashboardMetrics->handle()]);
    }
}
