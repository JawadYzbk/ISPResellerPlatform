<?php

namespace App\Http\Controllers\Api;

use App\Actions\StoreMediaUpload;
use App\Http\Controllers\Controller;
use App\Models\MediaUpload;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

final class TechnicianMediaController extends Controller
{
    public function store(Request $request, StoreMediaUpload $store): JsonResponse
    {
        $actor = $this->ensureTechnician($request);
        $request->validate([
            'file' => ['required', 'file', 'max:20480', 'mimetypes:image/jpeg,image/png,image/webp'],
        ]);
        $file = $request->file('file');
        abort_unless($file instanceof UploadedFile, 422, 'A media file is required.');
        $media = $store->handle($file, $actor);

        return response()->json($this->payload($media), 201);
    }

    public function storeForWorkOrder(Request $request, string $workOrder, StoreMediaUpload $store): JsonResponse
    {
        $actor = $this->ensureTechnician($request);
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:20480', 'mimetypes:image/jpeg,image/png,image/webp'],
            'purpose' => ['nullable', Rule::in(['evidence', 'other'])],
        ]);
        $order = WorkOrder::query()
            ->where('public_id', $workOrder)
            ->where('assigned_to', $actor->id)
            ->firstOrFail();
        $file = $request->file('file');
        abort_unless($file instanceof UploadedFile, 422, 'A media file is required.');
        $media = $store->handle($file, $actor, $order, $validated['purpose'] ?? 'evidence');

        return response()->json($this->payload($media), 201);
    }

    /** @return array<string, mixed> */
    private function payload(MediaUpload $media): array
    {
        $media->loadMissing('workOrder');

        return [
            'id' => $media->public_id,
            'filename' => $media->original_name,
            'mime_type' => $media->mime_type,
            'size_bytes' => $media->size_bytes,
            'purpose' => $media->purpose,
            'work_order_id' => $media->workOrder?->public_id,
        ];
    }

    private function ensureTechnician(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('workorders.complete'), 403);

        return $user;
    }
}
