<?php

namespace App\Services;

use App\Enums\QueueStatus;
use App\Enums\QueueTokenStatus;
use App\Models\Queue;
use App\Models\QueueToken;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class QueueRotationService
{
    /**
     * Close the active queue and open a fresh one for the same shift, service, and doctor.
     *
     * Tokens (including reserved appointments) remain on the closed queue; only the
     * currently serving token, if any, is marked done.
     */
    public function endCurrentAndStartNew(Queue $queue): Queue
    {
        if (! $queue->isActive()) {
            throw new RuntimeException(__('This queue is no longer active.'));
        }

        return DB::transaction(function () use ($queue): Queue {
            $locked = Queue::query()->lockForUpdate()->findOrFail($queue->id);

            if (! $locked->isActive()) {
                throw new RuntimeException(__('This queue is no longer active.'));
            }

            $serving = QueueToken::query()
                ->where('queue_id', $locked->id)
                ->where('status', QueueTokenStatus::Serving)
                ->first();

            if ($serving) {
                $serving->update([
                    'status' => QueueTokenStatus::Done,
                    'completed_at' => now(),
                ]);
            }

            $locked->forceFill([
                'status' => QueueStatus::Closed,
                'closed_at' => now(),
            ])->save();

            return Queue::findOrCreateActiveForShift(
                (int) $locked->service_id,
                $locked->doctor_id,
                (int) $locked->shift_id
            );
        });
    }
}
