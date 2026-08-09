<?php

namespace App\Http\Controllers\Auth;

use App\Actions\ReauthenticateUser;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ReauthenticateController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Reauthenticate');
    }

    public function store(Request $request, ReauthenticateUser $reauthenticateUser): RedirectResponse
    {
        $validated = $request->validate(['password' => ['required', 'string']]);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        if (! $reauthenticateUser->handle($user, $validated['password'])) {
            return back()->withErrors(['password' => 'That password is not valid.']);
        }

        return redirect()->intended(route('dashboard'))->with('success', 'Identity confirmed.');
    }
}
