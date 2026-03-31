<?php

namespace App\Services;

use App\Enums\QueueStatus;
use App\Models\Queue;
use App\Models\QueueToken;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class QueueNormalizationService
{
    /**
     * Normalize active queues by (service_id, doctor_id, shift_id).
     *
     * - Deterministically picks a "primary" active queue (lowest id).
     * - Closes empty duplicates.
     * - Moves non-conflicting tokens from duplicates into the primary queue.
     * - Leaves conflicting duplicates open and reports them.
     *
     * @return array{
     *   scanned_groups:int,
     *   closed_empty_duplicates:int,
     *   merged_tokens:int,
     *   closed_after_merge:int,
     *   conflicts:list<array{service_id:int, doctor_id:int|null, shift_id:int, primary_queue_id:int, conflicting_queue_id:int, token_numbers:list<int>}>
     * }
     */
    public function normalizeActiveQueues(): array
    {
        return DB::transaction(function (): array {
            /** @var Collection<int, Queue> $queues */
            $queues = Queue::query()
                ->whereNull('closed_at')
                ->where('status', QueueStatus::Active)
                ->lockForUpdate()
                ->orderBy('id')
                ->get();

            $byKey = $queues->groupBy(fn (Queue $q) => implode(':', [
                (int) $q->service_id,
                $q->doctor_id === null ? 'null' : (int) $q->doctor_id,
                (int) $q->shift_id,
            ]));

            $report = [
                'scanned_groups' => 0,
                'closed_empty_duplicates' => 0,
                'merged_tokens' => 0,
                'closed_after_merge' => 0,
                'conflicts' => [],
            ];

            foreach ($byKey as $group) {
                if ($group->count() <= 1) {
                    continue;
                }

                $report['scanned_groups']++;

                /** @var Queue $primary */
                $primary = $group->first();

                $primaryTokenNumbers = QueueToken::query()
                    ->where('queue_id', $primary->id)
                    ->pluck('token_number')
                    ->map(fn ($n) => (int) $n)
                    ->all();

                $primaryTokenSet = array_fill_keys($primaryTokenNumbers, true);

                foreach ($group->skip(1) as $dupe) {
                    $dupeTokens = QueueToken::query()
                        ->where('queue_id', $dupe->id)
                        ->orderBy('id')
                        ->get();

                    if ($dupeTokens->isEmpty()) {
                        $dupe->forceFill([
                            'status' => QueueStatus::Closed,
                            'closed_at' => now(),
                        ])->save();
                        $report['closed_empty_duplicates']++;

                        continue;
                    }

                    $conflicting = $dupeTokens
                        ->pluck('token_number')
                        ->map(fn ($n) => (int) $n)
                        ->filter(fn (int $n) => isset($primaryTokenSet[$n]))
                        ->values()
                        ->all();

                    if ($conflicting !== []) {
                        $report['conflicts'][] = [
                            'service_id' => (int) $dupe->service_id,
                            'doctor_id' => $dupe->doctor_id !== null ? (int) $dupe->doctor_id : null,
                            'shift_id' => (int) $dupe->shift_id,
                            'primary_queue_id' => (int) $primary->id,
                            'conflicting_queue_id' => (int) $dupe->id,
                            'token_numbers' => $conflicting,
                        ];

                        continue;
                    }

                    $moved = QueueToken::query()
                        ->where('queue_id', $dupe->id)
                        ->update(['queue_id' => $primary->id]);

                    $report['merged_tokens'] += (int) $moved;

                    $dupe->forceFill([
                        'status' => QueueStatus::Closed,
                        'closed_at' => now(),
                    ])->save();
                    $report['closed_after_merge']++;

                    foreach ($dupeTokens as $t) {
                        $primaryTokenSet[(int) $t->token_number] = true;
                    }
                }

                $maxTokenInPrimary = QueueToken::query()
                    ->where('queue_id', $primary->id)
                    ->max('token_number');

                $maxToken = max((int) $primary->current_token, (int) ($maxTokenInPrimary ?? 0));

                if ((int) $primary->current_token !== $maxToken) {
                    $primary->update(['current_token' => $maxToken]);
                }
            }

            return $report;
        });
    }
}
