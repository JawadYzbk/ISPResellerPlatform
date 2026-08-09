<?php

namespace App\Http\Controllers\Auth;

use App\Actions\AuthenticateUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class LoginController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(LoginRequest $request, AuthenticateUser $authenticateUser): RedirectResponse
    {
        $authenticated = $authenticateUser->handle(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $request->boolean('remember'),
        );

        if (! $authenticated) {
            return back()->withErrors(['email' => 'Those credentials do not match our records.'])->onlyInput('email');
        }

        $request->session()->regenerate();
        $request->user()->forceFill(['last_authenticated_at' => now()])->save();

        return redirect()->intended(route('dashboard'))->with('success', 'Welcome back.');
    }
}
