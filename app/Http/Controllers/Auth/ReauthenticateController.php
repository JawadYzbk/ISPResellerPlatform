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
    public function create(Request $request): Response
    {
        if (! $request->session()->has('url.intended')) {
            $this->rememberPreviousUrl($request);
        }

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

    private function rememberPreviousUrl(Request $request): void
    {
        $previous = $request->headers->get('referer');
        if (! is_string($previous) || trim($previous) === '') {
            return;
        }

        $parsed = parse_url($previous);
        if (! is_array($parsed) || ! isset($parsed['host']) || strcasecmp((string) $parsed['host'], $request->getHost()) !== 0) {
            return;
        }

        $path = (string) ($parsed['path'] ?? '');
        if ($path === route('security.reauthenticate', absolute: false)) {
            return;
        }

        $request->session()->put('url.intended', $previous);
    }
}
