<?php

namespace App\Http\Controllers\Web;

use App\Actions\SearchWorkspace;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WorkspaceSearchController extends Controller
{
    public function __invoke(Request $request, SearchWorkspace $searchWorkspace): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:120']]);

        return response()->json(['results' => $searchWorkspace->handle($user, (string) ($validated['q'] ?? ''))]);
    }
}
