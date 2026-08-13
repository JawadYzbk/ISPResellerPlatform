<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Security\TwoFactorService;
use App\Support\WorkspacePageCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TwoFactorController extends Controller
{
    public function setup(Request $request, TwoFactorService $twoFactor, WorkspacePageCatalog $pages): Response|RedirectResponse
    {
        $user = $this->user($request);
        if ($twoFactor->enabled($user)) {
            return redirect()->intended($pages->defaultDestination($user));
        }

        $setup = $request->session()->get('two_factor_setup');
        if (! is_array($setup)) {
            $setup = $twoFactor->begin($user);
            $request->session()->put('two_factor_setup', $setup);
        }

        return Inertia::render('Auth/TwoFactor/Setup', [
            'provisioningUri' => $setup['provisioning_uri'],
            'secret' => $setup['secret'],
            'recoveryCodes' => $setup['recovery_codes'],
        ]);
    }

    public function confirm(Request $request, TwoFactorService $twoFactor, WorkspacePageCatalog $pages): RedirectResponse
    {
        $validated = $request->validate(['code' => ['required', 'digits:6']]);
        $user = $this->user($request);

        if (! $twoFactor->confirm($user, $validated['code'])) {
            return back()->withErrors(['code' => 'That code is not valid.']);
        }

        $request->session()->put('two_factor_verified_user_id', $user->id);
        $request->session()->forget('two_factor_setup');

        return redirect()->intended($pages->defaultDestination($user))->with('success', 'Two-factor authentication enabled.');
    }

    public function challenge(): Response
    {
        return Inertia::render('Auth/TwoFactor/Challenge');
    }

    public function verify(Request $request, TwoFactorService $twoFactor, WorkspacePageCatalog $pages): RedirectResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'min:6', 'max:16']]);
        $user = $this->user($request);

        if (! $twoFactor->verify($user, $validated['code'])) {
            return back()->withErrors(['code' => 'That authentication code is not valid.']);
        }

        $request->session()->put('two_factor_verified_user_id', $user->id);

        return redirect()->intended($pages->defaultDestination($user));
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }
}
