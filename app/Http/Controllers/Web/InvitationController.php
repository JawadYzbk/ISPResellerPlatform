<?php

namespace App\Http\Controllers\Web;

use App\Actions\AcceptInvitation;
use App\Http\Controllers\Controller;
use App\Http\Requests\AcceptInvitationRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class InvitationController extends Controller
{
    public function show(string $token): Response
    {
        return Inertia::render('Auth/AcceptInvitation', ['token' => $token]);
    }

    public function accept(AcceptInvitationRequest $request, string $token, AcceptInvitation $accept): RedirectResponse
    {
        $accept->handle($token, (string) $request->validated('name'), (string) $request->validated('password'));

        return redirect()->route('login')->with('success', 'Account created. You can now sign in.');
    }
}
