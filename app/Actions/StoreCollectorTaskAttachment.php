<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\CollectorTaskMessage;
use App\Models\MediaUpload;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final readonly class StoreCollectorTaskAttachment implements Action
{
    public function handle(UploadedFile $file, User $actor, CollectorTaskMessage $message): MediaUpload
    {
        if ((int) $actor->tenant_id !== (int) $message->tenant_id) {
            throw new \LogicException('The message does not belong to the active workspace.');
        }
        $publicId = (string) Str::ulid();
        $extension = $file->guessExtension() ?: 'bin';
        $disk = (string) config('filesystems.default', 'local');
        $path = 'collector-tasks/'.$message->tenant_id.'/'.$message->collector_task_id.'/'.$publicId.'.'.$extension;
        $stored = Storage::disk($disk)->putFileAs(dirname($path), $file, basename($path));
        if ($stored === false) {
            throw new RuntimeException('The task attachment could not be stored.');
        }

        try {
            return MediaUpload::create([
                'tenant_id' => $message->tenant_id,
                'uploaded_by_id' => $actor->id,
                'collector_task_message_id' => $message->id,
                'public_id' => $publicId,
                'disk' => $disk,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => (string) $file->getMimeType(),
                'size_bytes' => (int) $file->getSize(),
                'sha256' => (string) hash_file('sha256', $file->getRealPath()),
                'purpose' => 'collector_task_attachment',
            ]);
        } catch (\Throwable $exception) {
            Storage::disk($disk)->delete($path);
            throw $exception;
        }
    }
}
