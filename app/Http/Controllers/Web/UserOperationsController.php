<?php

namespace App\Http\Controllers\Web;

use App\Actions\InviteUser;
use App\Actions\ListTenantUsers;
use App\Http\Controllers\Controller;
use App\Http\Requests\InviteUserRequest;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

final class UserOperationsController extends Controller
{
    public function index(Request $request, ListTenantUsers $listUsers): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('users.manage'), 403);
        $users = $listUsers->handle($request->string('search')->toString() ?: null);
        $rows = $users->getCollection()->map(fn (User $member): array => [
            'id' => $member->id,
            'name' => $member->name,
            'email' => $member->email,
            'role' => $member->role,
            'locale' => $member->locale,
            'timezone' => $member->timezone,
            'email_verified' => $member->email_verified_at !== null,
            'two_factor_enabled' => $member->two_factor_confirmed_at !== null,
        ])->values();
        $users = new LengthAwarePaginator(
            $rows,
            $users->total(),
            $users->perPage(),
            $users->currentPage(),
            ['path' => $request->url(), 'query' => $request->query()],
        );
        $invitations = Invitation::query()
            ->whereNull('accepted_at')
            ->latest()
            ->limit(10)
            ->get(['email', 'role', 'expires_at', 'created_at'])
            ->map(fn (Invitation $invitation): array => [
                'email' => $invitation->email,
                'role' => $invitation->role,
                'expires_at' => $invitation->expires_at?->toIso8601String(),
                'created_at' => $invitation->created_at?->toIso8601String(),
            ])->values()->all();

        return Inertia::render('Settings/Users', [
            'users' => $users,
            'invitations' => $invitations,
            'roles' => InviteUserRequest::INVITABLE_ROLES,
            'filters' => $request->only(['search']),
            'invitation' => $request->session()->get('invitation'),
        ]);
    }

    public function invite(InviteUserRequest $request, InviteUser $invite): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('users.manage'), 403);
        $result = $invite->handle($user, (string) $request->validated('email'), (string) $request->validated('role'));

        return redirect()->route('settings.users')->with('invitation', [
            'email' => $result['invitation']->email,
            'role' => $result['invitation']->role,
            'token' => $result['token'],
            'expires_at' => $result['invitation']->expires_at?->toIso8601String(),
        ])->with('success', 'Invitation created. Copy the one-time link before leaving this page.');
    }
}
