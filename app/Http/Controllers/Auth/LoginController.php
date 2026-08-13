<?php

namespace App\Http\Controllers\Auth;

use App\Actions\AuthenticateUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Support\WorkspacePageCatalog;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class LoginController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(LoginRequest $request, AuthenticateUser $authenticateUser, WorkspacePageCatalog $pages): RedirectResponse
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

        $user = $request->user();
        $destination = $user instanceof User ? $pages->defaultDestination($user) : route('dashboard');
        $intended = $request->session()->get('url.intended');
        $intendedPath = is_string($intended) ? parse_url($intended, PHP_URL_PATH) : null;

        if (in_array($intendedPath, ['/', route('dashboard', absolute: false)], true)) {
            $request->session()->forget('url.intended');
        }

        return redirect()->intended($destination)
            ->with('success_title', 'Welcome back')
            ->with('success', 'You are signed in and ready to work.');
    }
}
