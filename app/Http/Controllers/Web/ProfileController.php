<?php

namespace App\Http\Controllers\Web;

use App\Actions\UpdateUserProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserProfileRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ProfileController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $this->user($request);

        return Inertia::render('Settings/Profile', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'locale' => $user->locale,
                'timezone' => $user->timezone,
            ],
        ]);
    }

    public function update(UpdateUserProfileRequest $request, UpdateUserProfile $update): RedirectResponse
    {
        $update->handle($this->user($request), $request->validated());

        return redirect()->route('profile')->with('success', 'Profile updated.');
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
