<?php

namespace App\Http\Controllers\Web;

use App\Actions\ChangeUserLocale;
use App\Actions\ListSessionDevices;
use App\Actions\RevokeSessionDevice;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class SecurityController extends Controller
{
    public function sessions(Request $request, ListSessionDevices $listSessionDevices): Response
    {
        $user = $this->user($request);

        return Inertia::render('Security/Sessions', ['sessions' => $listSessionDevices->handle($user, $request->session()->getId())]);
    }

    public function revoke(Request $request, string $session, RevokeSessionDevice $revokeSessionDevice): RedirectResponse
    {
        $user = $this->user($request);
        $revokeSessionDevice->handle($user, $session, $request->session()->getId());

        return back()->with('success', 'Session revoked.');
    }

    public function locale(Request $request, ChangeUserLocale $changeUserLocale): RedirectResponse
    {
        $validated = $request->validate(['locale' => ['required', 'in:en,ar,fr']]);
        $changeUserLocale->handle($this->user($request), $validated['locale']);

        return back()->with('success', 'Language preference updated.');
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
