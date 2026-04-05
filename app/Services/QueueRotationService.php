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
     * Close the active queue. A new queue for the same service, doctor, and shift is created
     * automatically the next time a walk-in or appointment reserves a token.
     *
     * Tokens (including reserved appointments) remain on the closed queue; only the
     * currently serving token, if any, is marked done.
     */
    public function closeActive(Queue $queue): void
    {
        if (! $queue->isActive()) {
            throw new RuntimeException(__('This queue is no longer active.'));
        }

        DB::transaction(function () use ($queue): void {
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
        });
    }
}
