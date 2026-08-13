<?php

use App\Models\MessageTemplate;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('shows and updates WhatsApp templates for every supported locale', function (): void {
    $tenant = Tenant::factory()->create(['slug' => 'template-editor']);
    app(Tenancy::class)->set($tenant);
    $user = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Settings owner',
        'email' => 'template-editor@example.test',
        'password' => Hash::make('password'),
        'role' => 'tenant_owner',
        'last_authenticated_at' => now(),
    ]);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('tenant_owner');
    $user->forceFill(['last_authenticated_at' => now()])->save();

    $this->actingAs($user)
        ->get(route('settings.notification-templates'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Settings/NotificationTemplates')
            ->where('locales', ['en', 'ar', 'fr'])
            ->where('templates', fn ($templates): bool => count($templates) === 27)
            ->where('catalog.0.variables', fn ($variables): bool => collect($variables)->contains('customer_name'))
        );

    app(Tenancy::class)->set($tenant);
    $template = MessageTemplate::query()->where('key', 'customer.welcome')->where('channel', 'whatsapp')->where('locale', 'ar')->firstOrFail();
    $this->actingAs($user)
        ->patch(route('settings.notification-templates.update', $template->id), [
            'subject' => '',
            'body' => 'أهلاً {{ customer_name }}، رمزك {{ customer_code }}.',
        ])
        ->assertRedirect(route('settings.notification-templates'));

    app(Tenancy::class)->set($tenant);
    expect($template->refresh()->body)->toContain('{{ customer_name }}');

    $this->actingAs($user)
        ->patch(route('settings.notification-templates.update', $template->id), [
            'subject' => '',
            'body' => 'Hello {{ unknown_value }}',
        ])
        ->assertSessionHasErrors('body');
});
