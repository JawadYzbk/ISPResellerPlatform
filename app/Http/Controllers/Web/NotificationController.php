<?php

namespace App\Http\Controllers\Web;

use App\Actions\GetDashboardAttentionQueue;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class NotificationController extends Controller
{
    public function index(Request $request, GetDashboardAttentionQueue $getDashboardAttentionQueue): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return Inertia::render('Notifications/Index', [
            'attentionQueue' => $getDashboardAttentionQueue->handle(user: $user),
        ]);
    }
}
