<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\CollectorTask;
use App\Models\CollectorTaskMessage;
use App\Models\CollectorTaskRead;
use App\Models\User;
use App\Support\CollectorTaskAccess;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class AddCollectorTaskMessage implements Action
{
    public function __construct(private CollectorTaskAccess $access) {}

    public function handle(User $actor, CollectorTask $task, string $body): CollectorTaskMessage
    {
        if (! $this->access->canView($actor, $task)) {
            throw new DomainException('This task is not available to you.');
        }
        $body = trim($body);
        if ($body === '') {
            throw new DomainException('Write a message before sending.');
        }

        return DB::transaction(function () use ($actor, $task, $body): CollectorTaskMessage {
            $message = CollectorTaskMessage::create([
                'tenant_id' => $task->tenant_id,
                'collector_task_id' => $task->id,
                'author_id' => $actor->id,
                'body' => $body,
            ]);
            CollectorTaskRead::query()->updateOrCreate(
                ['collector_task_id' => $task->id, 'user_id' => $actor->id],
                ['tenant_id' => $task->tenant_id, 'last_read_at' => $message->created_at],
            );

            return $message->load('author:id,name,role');
        });
    }
}
