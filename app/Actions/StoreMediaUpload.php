<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\MediaUpload;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\Tenancy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final readonly class StoreMediaUpload implements Action
{
    public function handle(UploadedFile $file, User $actor, ?WorkOrder $workOrder = null, string $purpose = 'evidence'): MediaUpload
    {
        $tenantId = app(Tenancy::class)->requireId();
        if ($workOrder !== null && (int) $workOrder->tenant_id !== $tenantId) {
            throw new \LogicException('The work order does not belong to the active tenant.');
        }
        $publicId = (string) Str::ulid();
        $extension = $file->guessExtension() ?: 'bin';
        $disk = 'local';
        $path = 'media/'.$tenantId.'/'.$publicId.'.'.$extension;
        $stored = Storage::disk($disk)->putFileAs(dirname($path), $file, basename($path));
        if ($stored === false) {
            throw new RuntimeException('The media file could not be stored.');
        }

        try {
            return MediaUpload::create([
                'tenant_id' => $tenantId,
                'uploaded_by_id' => $actor->id,
                'work_order_id' => $workOrder?->id,
                'public_id' => $publicId,
                'disk' => $disk,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => (string) $file->getMimeType(),
                'size_bytes' => (int) $file->getSize(),
                'sha256' => (string) hash_file('sha256', $file->getRealPath()),
                'purpose' => $purpose,
            ]);
        } catch (\Throwable $exception) {
            Storage::disk($disk)->delete($path);
            throw $exception;
        }
    }
}
