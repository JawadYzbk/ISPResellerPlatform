<?php

namespace App\Http\Controllers\Web;

use App\Actions\CreatePublicBillingLink;
use App\Actions\RevokePublicBillingLink;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PublicBillingLink;
use App\Models\User;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class PublicBillingLinkController extends Controller
{
    public function invoice(Request $request, Invoice $invoice, CreatePublicBillingLink $create): RedirectResponse
    {
        $user = $this->user($request);
        $validated = $request->validate([
            'type' => ['required', Rule::in(['invoice', 'payment'])],
            'expires_in_days' => ['required', 'integer', 'min:1', 'max:90'],
        ]);
        $invoice->loadMissing('customer');

        return $this->created($request, fn () => $create->handle($user, $validated['type'], $invoice->customer, $invoice, null, $validated['expires_in_days']));
    }

    public function statement(Request $request, Customer $customer, CreatePublicBillingLink $create): RedirectResponse
    {
        $user = $this->user($request);
        $validated = $request->validate(['expires_in_days' => ['required', 'integer', 'min:1', 'max:90']]);

        return $this->created($request, fn () => $create->handle($user, 'statement', $customer, null, null, $validated['expires_in_days']));
    }

    public function receipt(Request $request, Payment $payment, CreatePublicBillingLink $create): RedirectResponse
    {
        $user = $this->user($request);
        $validated = $request->validate(['expires_in_days' => ['required', 'integer', 'min:1', 'max:90']]);
        $payment->loadMissing('customer');

        return $this->created($request, fn () => $create->handle($user, 'receipt', $payment->customer, null, $payment, $validated['expires_in_days']));
    }

    public function destroy(Request $request, PublicBillingLink $publicBillingLink, RevokePublicBillingLink $revoke): RedirectResponse
    {
        $user = $this->user($request);
        try {
            $revoke->handle($user, $publicBillingLink);
        } catch (DomainException $exception) {
            return back()->withErrors(['type' => $exception->getMessage()]);
        }

        return back()->with('success', 'Public billing link revoked.');
    }

    private function created(Request $request, callable $callback): RedirectResponse
    {
        try {
            $created = $callback();
        } catch (DomainException $exception) {
            return back()->withErrors(['type' => $exception->getMessage()]);
        }
        $url = route('public.billing.show', $created->token);
        $request->session()->flash('publicLink', ['url' => $url, 'expires_at' => $created->link->expires_at->toIso8601String()]);

        return back()->with('success', 'Public billing link created. Copy it before leaving this page.');
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('billing.invoices.view'), 403);

        return $user;
    }
}
