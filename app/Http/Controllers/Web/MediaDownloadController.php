<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\MediaUpload;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class MediaDownloadController extends Controller
{
    public function __invoke(Request $request, string $media): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('workorders.complete'), 403);
        $upload = MediaUpload::query()
            ->where('public_id', $media)
            ->whereNotNull('work_order_id')
            ->firstOrFail();
        abort_unless(Storage::disk($upload->disk)->exists($upload->path), 404);

        return Storage::disk($upload->disk)->download($upload->path, $upload->original_name, [
            'Content-Type' => $upload->mime_type,
        ]);
    }
}
