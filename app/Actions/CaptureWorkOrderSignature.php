<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Enums\WorkOrderStatus;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderSignature;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final readonly class CaptureWorkOrderSignature implements Action
{
    public function __construct(private StoreMediaUpload $storeMediaUpload) {}

    public function handle(WorkOrder $workOrder, User $actor, UploadedFile $file, string $signerName): WorkOrderSignature
    {
        if ((int) $workOrder->tenant_id !== (int) $actor->tenant_id) {
            throw new DomainException('The work order and signer must belong to the same tenant.');
        }
        if (in_array($workOrder->status, [WorkOrderStatus::Completed, WorkOrderStatus::Cancelled], true)) {
            throw new DomainException('A completed or cancelled work order cannot receive a new signature.');
        }
        $media = $this->storeMediaUpload->handle($file, $actor, $workOrder, 'signature');

        try {
            return DB::transaction(function () use ($workOrder, $actor, $media, $signerName): WorkOrderSignature {
                $locked = WorkOrder::query()->lockForUpdate()->findOrFail($workOrder->id);
                if (WorkOrderSignature::query()->where('work_order_id', $locked->id)->exists()) {
                    throw new DomainException('This work order already has a signature.');
                }

                return WorkOrderSignature::create([
                    'work_order_id' => $locked->id,
                    'media_upload_id' => $media->id,
                    'captured_by_id' => $actor->id,
                    'signer_name' => $signerName,
                    'signed_at' => now(),
                ]);
            });
        } catch (\Throwable $exception) {
            Storage::disk($media->disk)->delete($media->path);
            $media->delete();
            throw $exception;
        }
    }
}
