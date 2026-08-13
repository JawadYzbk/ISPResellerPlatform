<?php

namespace App\Http\Controllers\Web;

use App\Actions\UpdateUserProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserProfileRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Support\WorkspacePageCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ProfileController extends Controller
{
    public function show(Request $request, WorkspacePageCatalog $pages): Response
    {
        $user = $this->user($request);
        $tenant = Tenant::query()->find($user->tenant_id);

        return Inertia::render('Settings/Profile', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'locale' => $user->locale,
                'timezone' => $user->timezone,
                'default_view' => $user->default_view ?: '/dashboard',
            ],
            'workspaceLocale' => $tenant?->settingsData()->locale ?? 'en',
            'defaultViews' => array_map(
                fn (array $page): array => ['label' => $page['label'], 'detail' => $page['detail'], 'href' => $page['href']],
                $pages->defaultViewsFor($user),
            ),
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
