<?php

namespace App\Http\Controllers\Api;

use App\Actions\StoreMediaUpload;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

final class TechnicianMediaController extends Controller
{
    public function store(Request $request, StoreMediaUpload $store): JsonResponse
    {
        abort_unless($request->user()?->can('workorders.complete'), 403);
        $request->validate([
            'file' => ['required', 'file', 'max:20480', 'mimetypes:image/jpeg,image/png,image/webp'],
        ]);
        $file = $request->file('file');
        abort_unless($file instanceof UploadedFile, 422, 'A media file is required.');
        $media = $store->handle($file, $request->user());

        return response()->json([
            'id' => $media->public_id,
            'filename' => $media->original_name,
            'mime_type' => $media->mime_type,
            'size_bytes' => $media->size_bytes,
        ], 201);
    }
}
