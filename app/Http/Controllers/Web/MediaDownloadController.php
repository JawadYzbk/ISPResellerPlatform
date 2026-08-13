<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\MediaUpload;
use App\Models\User;
use App\Support\CollectorTaskAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class MediaDownloadController extends Controller
{
    public function __invoke(Request $request, string $media): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $upload = MediaUpload::query()
            ->with('collectorTaskMessage.task')
            ->where('public_id', $media)
            ->where(function (Builder $query): void {
                $query->whereNotNull('work_order_id')
                    ->orWhereNotNull('customer_id')
                    ->orWhereNotNull('collector_task_message_id');
            })
            ->firstOrFail();
        if ($upload->collector_task_message_id !== null) {
            $task = $upload->collectorTaskMessage?->task;
            abort_unless($task !== null && app(CollectorTaskAccess::class)->canView($user, $task), 404);
        } elseif ($upload->customer_id !== null) {
            abort_unless($user->can('customers.view'), 403);
        } else {
            abort_unless($user->can('workorders.complete'), 403);
        }
        abort_if($upload->retention_until?->isBefore(today()) === true, 410, 'This document is outside its retention period.');
        abort_unless(Storage::disk($upload->disk)->exists($upload->path), 404);

        return Storage::disk($upload->disk)->download($upload->path, $upload->original_name, [
            'Content-Type' => $upload->mime_type,
        ]);
    }
}
