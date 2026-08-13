<?php

namespace App\Http\Controllers\Web;

use App\Actions\CreateExpenseCategory;
use App\Actions\CreateExpenseVendor;
use App\Actions\CreateOperationalExpense;
use App\Actions\CreateRecurringExpenseSchedule;
use App\Actions\GetCurrencyCatalog;
use App\Actions\ReviewOperationalExpense;
use App\Actions\StoreMediaUpload;
use App\Actions\UpdateExpenseCategory;
use App\Actions\UpdateExpenseVendor;
use App\Actions\UpdateRecurringExpenseSchedule;
use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use App\Models\ExpenseVendor;
use App\Models\OperationalExpense;
use App\Models\RecurringExpenseSchedule;
use App\Models\User;
use App\Support\Tenancy;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class OperationalExpenseController extends Controller
{
    public function index(Request $request, GetCurrencyCatalog $currencies): Response
    {
        $user = $this->userWith($request, 'expenses.view');
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['all', ...OperationalExpense::STATUSES])],
            'payment_source' => ['nullable', Rule::in(['all', ...OperationalExpense::PAYMENT_SOURCES])],
            'category' => ['nullable', 'integer'],
        ]);
        $status = (string) ($validated['status'] ?? 'all');
        $paymentSource = (string) ($validated['payment_source'] ?? 'all');
        $categoryId = isset($validated['category']) ? (int) $validated['category'] : null;

        $expenses = OperationalExpense::query()
            ->with([
                'category:id,public_id,name,code', 'vendor:id,public_id,name', 'requestedBy:id,name',
                'reviewedBy:id,name', 'collector:id,name', 'attachments:id,operational_expense_id,public_id,original_name,mime_type,size_bytes',
            ])
            ->when($user->role === 'collector', fn ($query) => $query->where('collector_id', $user->id))
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($paymentSource !== 'all', fn ($query) => $query->where('payment_source', $paymentSource))
            ->when($categoryId !== null, fn ($query) => $query->where('expense_category_id', $categoryId))
            ->latest('incurred_at')
            ->limit(250)
            ->get();

        return Inertia::render('Operations/Expenses', [
            'filters' => ['status' => $status, 'payment_source' => $paymentSource, 'category' => $categoryId],
            'permissions' => [
                'create' => $user->can('expenses.create'),
                'approve' => $user->can('expenses.approve'),
                'manage' => $user->can('expenses.manage'),
            ],
            'categories' => ExpenseCategory::query()->orderByDesc('is_active')->orderBy('name')->get(['id', 'public_id', 'name', 'code', 'is_active']),
            'vendors' => ExpenseVendor::query()->orderByDesc('is_active')->orderBy('name')->get(['id', 'public_id', 'name', 'phone', 'email', 'tax_number', 'address', 'is_active']),
            'collectors' => User::query()->where('role', 'collector')->orderBy('name')->get(['id', 'name']),
            'currencies' => $currencies->handle(),
            'expenses' => $expenses->map(fn (OperationalExpense $expense): array => [
                'public_id' => $expense->public_id,
                'status' => $expense->status,
                'payment_source' => $expense->payment_source,
                'amount' => $expense->amount,
                'currency' => $expense->currency,
                'description' => $expense->description,
                'reference' => $expense->reference,
                'incurred_at' => $expense->incurred_at->toIso8601String(),
                'reviewed_at' => $expense->reviewed_at?->toIso8601String(),
                'review_note' => $expense->review_note,
                'category' => $expense->category?->only(['public_id', 'name', 'code']),
                'vendor' => $expense->vendor?->only(['public_id', 'name']),
                'requested_by' => $expense->requestedBy?->only(['id', 'name']),
                'reviewed_by' => $expense->reviewedBy?->only(['id', 'name']),
                'collector' => $expense->collector?->only(['id', 'name']),
                'attachments' => $expense->attachments->map(fn ($attachment): array => [
                    'public_id' => $attachment->public_id,
                    'name' => $attachment->original_name,
                    'mime_type' => $attachment->mime_type,
                    'size_bytes' => $attachment->size_bytes,
                    'download_url' => route('operations.media.download', $attachment->public_id),
                ])->values()->all(),
            ])->values(),
            'recurringSchedules' => RecurringExpenseSchedule::query()
                ->with(['category:id,name', 'vendor:id,name'])
                ->latest()
                ->get()
                ->map(fn (RecurringExpenseSchedule $schedule): array => [
                    'public_id' => $schedule->public_id,
                    'frequency' => $schedule->frequency,
                    'interval' => $schedule->interval,
                    'payment_source' => $schedule->payment_source,
                    'amount' => $schedule->amount,
                    'currency' => $schedule->currency,
                    'description' => $schedule->description,
                    'next_run_on' => $schedule->next_run_on->format('Y-m-d'),
                    'ends_on' => $schedule->ends_on?->format('Y-m-d'),
                    'is_active' => $schedule->is_active,
                    'category' => $schedule->category?->only(['name']),
                    'vendor' => $schedule->vendor?->only(['name']),
                ])->values(),
        ]);
    }

    public function store(Request $request, CreateOperationalExpense $create, StoreMediaUpload $storeMedia): RedirectResponse
    {
        $user = $this->userWith($request, 'expenses.create');
        $data = $request->validate([
            'expense_category_id' => ['required', 'integer'],
            'expense_vendor_id' => ['nullable', 'integer'],
            'collector_id' => ['nullable', 'integer'],
            'cash_shift_id' => ['nullable', 'integer'],
            'payment_source' => ['required', Rule::in(OperationalExpense::PAYMENT_SOURCES)],
            'amount' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'description' => ['required', 'string', 'max:2000'],
            'reference' => ['nullable', 'string', 'max:120'],
            'incurred_at' => ['nullable', 'date'],
            'attachment' => ['nullable', 'file', 'max:20480', 'mimetypes:application/pdf,image/jpeg,image/png,image/webp'],
        ]);
        try {
            $expense = $create->handle($user, $data);
        } catch (DomainException $exception) {
            return back()->withErrors(['description' => $exception->getMessage()]);
        }
        $attachment = $request->file('attachment');
        if ($attachment instanceof UploadedFile) {
            $storeMedia->handle($attachment, $user, purpose: 'expense_receipt', operationalExpense: $expense);
        }

        return back()->with('success', 'Expense submitted for approval.');
    }

    public function review(Request $request, OperationalExpense $operationalExpense, ReviewOperationalExpense $review): RedirectResponse
    {
        $user = $this->userWith($request, 'expenses.approve');
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['posted', 'rejected'])],
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);
        try {
            $review->handle($user, $operationalExpense, $validated['decision'], $validated['review_note'] ?? null);
        } catch (DomainException $exception) {
            return back()->withErrors(['review' => $exception->getMessage()]);
        }

        return back()->with('success', $validated['decision'] === 'posted' ? 'Expense approved and posted.' : 'Expense rejected.');
    }

    public function storeCategory(Request $request, CreateExpenseCategory $create): RedirectResponse
    {
        $this->userWith($request, 'expenses.manage');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('expense_categories', 'code')->where('tenant_id', app(Tenancy::class)->requireId()),
            ],
        ]);
        $create->handle($data);

        return back()->with('success', 'Expense category created.');
    }

    public function updateCategory(Request $request, ExpenseCategory $expenseCategory, UpdateExpenseCategory $update): RedirectResponse
    {
        $this->userWith($request, 'expenses.manage');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'is_active' => ['required', 'boolean'],
        ]);
        $update->handle($expenseCategory, $data);

        return back()->with('success', 'Expense category updated.');
    }

    public function storeVendor(Request $request, CreateExpenseVendor $create): RedirectResponse
    {
        $this->userWith($request, 'expenses.manage');
        $data = $request->validate($this->vendorRules());
        $create->handle($data);

        return back()->with('success', 'Expense vendor created.');
    }

    public function updateVendor(Request $request, ExpenseVendor $expenseVendor, UpdateExpenseVendor $update): RedirectResponse
    {
        $this->userWith($request, 'expenses.manage');
        $data = $request->validate([...$this->vendorRules(), 'is_active' => ['required', 'boolean']]);
        $update->handle($expenseVendor, $data);

        return back()->with('success', 'Expense vendor updated.');
    }

    public function storeRecurring(Request $request, CreateRecurringExpenseSchedule $create): RedirectResponse
    {
        $user = $this->userWith($request, 'expenses.manage');
        $data = $request->validate([
            'expense_category_id' => ['required', 'integer'],
            'expense_vendor_id' => ['nullable', 'integer'],
            'frequency' => ['required', Rule::in(RecurringExpenseSchedule::FREQUENCIES)],
            'interval' => ['required', 'integer', 'min:1', 'max:24'],
            'payment_source' => ['required', Rule::in(['cash', 'bank'])],
            'amount' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'description' => ['required', 'string', 'max:2000'],
            'reference' => ['nullable', 'string', 'max:120'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
        ]);
        $create->handle($user, $data);

        return back()->with('success', 'Recurring expense schedule created.');
    }

    public function updateRecurring(Request $request, RecurringExpenseSchedule $recurringExpenseSchedule, UpdateRecurringExpenseSchedule $update): RedirectResponse
    {
        $this->userWith($request, 'expenses.manage');
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $update->handle($recurringExpenseSchedule, $data['is_active']);

        return back()->with('success', $data['is_active'] ? 'Recurring expense resumed.' : 'Recurring expense paused.');
    }

    /** @return array<string, list<string>> */
    private function vendorRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'tax_number' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function userWith(Request $request, string $permission): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can($permission), 403);

        return $user;
    }
}
