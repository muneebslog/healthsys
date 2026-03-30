<?php

namespace App\Services;

use App\Enums\QueueTokenStatus;
use App\Models\Queue;
use App\Models\QueueToken;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class QueueCallingService
{
    public function callNext(Queue $queue): void
    {
        DB::transaction(function () use ($queue): void {
            $queue = Queue::query()->lockForUpdate()->findOrFail($queue->id);

            $serving = QueueToken::query()
                ->where('queue_id', $queue->id)
                ->where('status', QueueTokenStatus::Serving)
                ->first();

            if ($serving) {
                $serving->update([
                    'status' => QueueTokenStatus::Done,
                    'completed_at' => now(),
                ]);
            }

            $this->promoteNextWaiting($queue);
        });
    }

    public function skip(Queue $queue): void
    {
        DB::transaction(function () use ($queue): void {
            $queue = Queue::query()->lockForUpdate()->findOrFail($queue->id);

            $serving = QueueToken::query()
                ->where('queue_id', $queue->id)
                ->where('status', QueueTokenStatus::Serving)
                ->first();

            if (! $serving) {
                throw new RuntimeException('No token is currently being served.');
            }

            $serving->update(['status' => QueueTokenStatus::Skipped]);

            $this->promoteNextWaiting($queue);
        });
    }

    public function previous(Queue $queue): void
    {
        DB::transaction(function () use ($queue): void {
            $queue = Queue::query()->lockForUpdate()->findOrFail($queue->id);

            $lastDone = QueueToken::query()
                ->where('queue_id', $queue->id)
                ->where('status', QueueTokenStatus::Done)
                ->whereNotNull('completed_at')
                ->orderByDesc('completed_at')
                ->orderByDesc('id')
                ->first();

            if (! $lastDone) {
                throw new RuntimeException('Nothing to step back to.');
            }

            $currentServing = QueueToken::query()
                ->where('queue_id', $queue->id)
                ->where('status', QueueTokenStatus::Serving)
                ->first();

            if ($currentServing) {
                $currentServing->update([
                    'status' => QueueTokenStatus::Waiting,
                    'called_at' => null,
                ]);
            }

            $lastDone->update([
                'status' => QueueTokenStatus::Serving,
                'completed_at' => null,
                'called_at' => now(),
            ]);

            $queue->update(['current_flow_token' => $lastDone->token_number]);
        });
    }

    public function requeue(QueueToken $token): void
    {
        if ($token->status !== QueueTokenStatus::Skipped) {
            throw new RuntimeException('Only skipped tokens can be re-queued.');
        }

        $token->update(['status' => QueueTokenStatus::Waiting]);
    }

    private function promoteNextWaiting(Queue $queue): void
    {
        $next = QueueToken::query()
            ->where('queue_id', $queue->id)
            ->where('status', QueueTokenStatus::Waiting)
            ->orderBy('token_number')
            ->first();

        if ($next) {
            $next->update([
                'status' => QueueTokenStatus::Serving,
                'called_at' => now(),
            ]);
            $queue->update(['current_flow_token' => $next->token_number]);

            return;
        }

        $queue->update(['current_flow_token' => 0]);
    }
}
