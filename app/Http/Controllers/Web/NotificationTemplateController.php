<?php

namespace App\Http\Controllers\Web;

use App\Actions\SaveMessageTemplate;
use App\Http\Controllers\Controller;
use App\Models\MessageTemplate;
use App\Models\Tenant;
use App\Models\User;
use App\Support\MessageTemplateProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class NotificationTemplateController extends Controller
{
    public function index(Request $request, MessageTemplateProvisioner $provisioner): Response
    {
        $user = $this->authorizedUser($request);
        $tenant = $this->tenant($user);
        $provisioner->provision($tenant, channel: 'whatsapp');

        $templates = MessageTemplate::query()
            ->where('channel', 'whatsapp')
            ->orderBy('key')
            ->orderBy('locale')
            ->get()
            ->map(fn (MessageTemplate $template): array => [
                'id' => $template->id,
                'key' => $template->key,
                'channel' => $template->channel,
                'locale' => $template->locale,
                'subject' => $template->subject,
                'body' => $template->body,
                'variables' => $template->variables ?? [],
                'is_active' => $template->is_active,
            ])
            ->values();

        return Inertia::render('Settings/NotificationTemplates', [
            'templates' => $templates,
            'catalog' => $provisioner->catalog(),
            'locales' => $provisioner->supportedLocales(),
            'storageWarning' => $provisioner->storageWarning(),
        ]);
    }

    public function update(Request $request, int $messageTemplate, MessageTemplateProvisioner $provisioner, SaveMessageTemplate $save): RedirectResponse
    {
        $user = $this->authorizedUser($request);
        $template = MessageTemplate::withoutGlobalScopes()->findOrFail($messageTemplate);
        $this->assertTenant($user, $template->tenant_id);
        abort_unless($template->channel === 'whatsapp', 404);

        $validated = $request->validate([
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
        ]);
        if ($provisioner->storageWarning() !== null && (! $this->isAscii((string) ($validated['subject'] ?? '')) || ! $this->isAscii((string) $validated['body']))) {
            throw ValidationException::withMessages(['body' => 'This database encoding cannot store Arabic or French characters. Recreate PostgreSQL with UTF-8 before saving translated text.']);
        }
        $definition = collect($provisioner->catalog())->firstWhere('key', $template->key) ?? [];
        $variables = is_array($definition['variables'] ?? null) ? $definition['variables'] : [];
        preg_match_all('/\{\{\s*([a-zA-Z0-9_.-]+)\s*\}\}/', (string) $validated['body'], $matches);
        $unknown = array_values(array_diff(array_unique($matches[1] ?? []), $variables));
        if ($unknown !== []) {
            throw ValidationException::withMessages(['body' => 'Unknown variable(s): '.implode(', ', $unknown).'. Use one of the listed variables.']);
        }

        $save->handle($template, $validated);

        return redirect()->route('settings.notification-templates')->with('success', "{$template->key} ({$template->locale}) updated.");
    }

    private function authorizedUser(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('settings.manage'), 403);

        return $user;
    }

    private function tenant(User $user): Tenant
    {
        abort_unless($user->tenant instanceof Tenant, 403);

        return $user->tenant;
    }

    private function assertTenant(User $user, int $tenantId): void
    {
        abort_unless($user->tenant_id === $tenantId, 404);
    }

    private function isAscii(string $value): bool
    {
        return preg_match('/[^\x00-\x7F]/', $value) !== 1;
    }
}
