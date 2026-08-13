<?php

namespace App\Http\Controllers\Web;

use App\Actions\AddCollectorTaskMessage;
use App\Actions\CreateCollectorTask;
use App\Actions\DiscardCollectorTaskMessage;
use App\Actions\MarkCollectorTaskRead;
use App\Actions\StoreCollectorTaskAttachment;
use App\Actions\UpdateCollectorTaskStatus;
use App\Http\Controllers\Controller;
use App\Models\CollectorTask;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CollectorTaskAccess;
use App\Support\CollectorTaskPresenter;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

final class CollectorTaskController extends Controller
{
    public function index(Request $request, CollectorTaskPresenter $presenter, MarkCollectorTaskRead $markRead): Response
    {
        $actor = $this->manager($request);
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['open', ...CollectorTask::STATUSES])],
            'collector' => ['nullable', 'integer'],
            'task' => ['nullable', 'string', 'max:32'],
        ]);
        $query = CollectorTask::query()
            ->with(['collector:id,name,email', 'createdBy:id,name', 'customer:id,public_id,code,first_name,last_name,phone,address', 'reads'])
            ->withCount('messages')
            ->latest('updated_at');
        $status = (string) ($validated['status'] ?? 'open');
        if ($status === 'open') {
            $query->whereNotIn('status', ['completed', 'cancelled']);
        } else {
            $query->where('status', $status);
        }
        if (isset($validated['collector'])) {
            $query->where('collector_id', (int) $validated['collector']);
        }
        $tasks = $query->limit(150)->get();
        $selected = isset($validated['task'])
            ? CollectorTask::query()->where('public_id', $validated['task'])->first()
            : $tasks->first();
        if ($selected instanceof CollectorTask) {
            $markRead->handle($actor, $selected);
        }
        $tenant = Tenant::query()->findOrFail($actor->tenant_id);

        return Inertia::render('Operations/CollectorTasks', [
            'filters' => ['status' => $status, 'collector' => isset($validated['collector']) ? (int) $validated['collector'] : null],
            'collectors' => User::query()->where('role', 'collector')->orderBy('name')->get(['id', 'name', 'email']),
            'customers' => Customer::query()->orderBy('first_name')->limit(2000)->get()->map(fn (Customer $customer): array => [
                'id' => $customer->id,
                'code' => $customer->code,
                'name' => $customer->full_name,
                'phone' => $customer->phone,
            ]),
            'tasks' => $tasks->map(fn (CollectorTask $task): array => $presenter->make($task, $actor, false))->values(),
            'selectedTask' => $selected instanceof CollectorTask ? $presenter->make($selected, $actor) : null,
            'timezone' => $tenant->settingsData()->timezone,
        ]);
    }

    public function store(Request $request, CreateCollectorTask $create): RedirectResponse
    {
        $actor = $this->manager($request);
        $tenantId = (int) $actor->tenant_id;
        $validated = $request->validate([
            'collector_id' => ['required', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('role', 'collector'))],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['required', Rule::in(CollectorTask::PRIORITIES)],
            'due_at' => ['nullable', 'date'],
        ]);
        try {
            $task = $create->handle(
                $actor,
                User::query()->findOrFail($validated['collector_id']),
                isset($validated['customer_id']) ? Customer::query()->findOrFail($validated['customer_id']) : null,
                $validated,
            );
        } catch (DomainException $exception) {
            return back()->withErrors(['task' => $exception->getMessage()]);
        }

        return redirect()->route('operations.collector-tasks', ['task' => $task->public_id])->with('success', 'Collector task created.');
    }

    public function message(Request $request, CollectorTask $collectorTask, AddCollectorTaskMessage $add, StoreCollectorTaskAttachment $storeAttachment, DiscardCollectorTaskMessage $discard, CollectorTaskPresenter $presenter): RedirectResponse|JsonResponse
    {
        $actor = $this->participant($request, $collectorTask);
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp,txt', 'max:10240'],
        ]);
        try {
            $message = $add->handle($actor, $collectorTask, $validated['body']);
            if ($request->hasFile('attachment')) {
                $storeAttachment->handle($request->file('attachment'), $actor, $message);
            }
        } catch (DomainException $exception) {
            return $request->expectsJson()
                ? response()->json(['message' => $exception->getMessage()], 422)
                : back()->withErrors(['message' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            if (isset($message)) {
                $discard->handle($message);
            }
            report($exception);

            return $request->expectsJson()
                ? response()->json(['message' => 'The attachment could not be stored.'], 422)
                : back()->withErrors(['attachment' => 'The attachment could not be stored.']);
        }

        return $request->expectsJson()
            ? response()->json(['message' => 'Message sent.', 'data' => $presenter->make($collectorTask->refresh(), $actor)], 201)
            : back()->with('success', 'Message sent.');
    }

    public function status(Request $request, CollectorTask $collectorTask, UpdateCollectorTaskStatus $update, CollectorTaskPresenter $presenter): RedirectResponse|JsonResponse
    {
        $actor = $this->participant($request, $collectorTask);
        $validated = $request->validate(['status' => ['required', Rule::in(CollectorTask::STATUSES)]]);
        try {
            $task = $update->handle($actor, $collectorTask, $validated['status']);
        } catch (DomainException $exception) {
            return $request->expectsJson()
                ? response()->json(['message' => $exception->getMessage()], 422)
                : back()->withErrors(['status' => $exception->getMessage()]);
        }

        return $request->expectsJson()
            ? response()->json(['message' => 'Task updated.', 'data' => $presenter->make($task, $actor)])
            : back()->with('success', 'Collector task updated.');
    }

    public function read(Request $request, CollectorTask $collectorTask, MarkCollectorTaskRead $mark): JsonResponse
    {
        $actor = $this->participant($request, $collectorTask);
        $mark->handle($actor, $collectorTask);

        return response()->json(['message' => 'Task marked as read.']);
    }

    private function manager(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('reports.operations'), 403);

        return $user;
    }

    private function participant(Request $request, CollectorTask $task): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless(app(CollectorTaskAccess::class)->canView($user, $task), 404);

        return $user;
    }
}
