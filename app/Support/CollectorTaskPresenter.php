<?php

namespace App\Support;

use App\Models\CollectorTask;
use App\Models\CollectorTaskMessage;
use App\Models\CollectorTaskRead;
use App\Models\MediaUpload;
use App\Models\User;

final class CollectorTaskPresenter
{
    /** @return array<string, mixed> */
    public function make(CollectorTask $task, User $viewer, bool $withMessages = true): array
    {
        $task->loadMissing(['collector:id,name,email', 'createdBy:id,name', 'customer:id,public_id,code,first_name,last_name,phone,address', 'reads']);
        if ($withMessages) {
            $task->loadMissing(['messages.author:id,name,role', 'messages.attachments']);
        }
        $read = $task->reads->first(fn (CollectorTaskRead $item): bool => (int) $item->user_id === (int) $viewer->id);
        $lastMessageAt = $task->messages()->max('created_at');
        $unread = $lastMessageAt !== null && ($read === null || $read->last_read_at->lt($lastMessageAt));

        return [
            'id' => $task->public_id,
            'title' => $task->title,
            'description' => $task->description,
            'priority' => $task->priority,
            'status' => $task->status,
            'due_at' => $task->due_at?->toIso8601String(),
            'completed_at' => $task->completed_at?->toIso8601String(),
            'created_at' => $task->created_at?->toIso8601String(),
            'unread' => $unread,
            'collector' => ['id' => $task->collector->id, 'name' => $task->collector->name, 'email' => $task->collector->email],
            'created_by' => ['name' => $task->createdBy->name],
            'customer' => $task->customer === null ? null : [
                'id' => $task->customer->public_id,
                'code' => $task->customer->code,
                'name' => $task->customer->full_name,
                'phone' => $task->customer->phone,
                'address' => $task->customer->address,
            ],
            'messages' => $withMessages ? $task->messages->map(fn (CollectorTaskMessage $message): array => [
                'id' => $message->public_id,
                'body' => $message->body,
                'created_at' => $message->created_at?->toIso8601String(),
                'author' => [
                    'id' => $message->author->id,
                    'name' => $message->author->name,
                    'role' => $message->author->role,
                    'is_viewer' => (int) $message->author_id === (int) $viewer->id,
                ],
                'attachments' => $message->attachments->map(fn (MediaUpload $attachment): array => [
                    'id' => $attachment->public_id,
                    'name' => $attachment->original_name,
                    'mime_type' => $attachment->mime_type,
                    'size_bytes' => $attachment->size_bytes,
                    'download_url' => route('operations.media.download', $attachment->public_id),
                ])->values()->all(),
            ])->values() : [],
        ];
    }
}
