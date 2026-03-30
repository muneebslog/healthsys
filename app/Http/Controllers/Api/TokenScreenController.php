<?php

namespace App\Http\Controllers\Api;

use App\Enums\QueueTokenStatus;
use App\Http\Controllers\Controller;
use App\Models\Queue;
use App\Models\QueueToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TokenScreenController extends Controller
{
    public function queues(): JsonResponse
    {
        $queues = Queue::query()
            ->active()
            ->with([
                'service:id,name',
                'doctor:id,name',
            ])
            ->orderBy('id')
            ->get();

        $payload = $queues->map(function (Queue $queue): array {
            return [
                'queue_id' => $queue->id,
                'doctor_name' => $queue->doctor?->name,
                'service_name' => $queue->service?->name ?? 'Service',
                'remaining_count' => $this->waitingCount($queue->id),
            ];
        })->values();

        return response()->json($payload);
    }

    public function data(Request $request): JsonResponse
    {
        $queueId = $request->query('queue_id');
        if ($queueId === null || $queueId === '') {
            return response()->json(['message' => 'queue_id is required.'], 422);
        }

        $queue = Queue::query()
            ->active()
            ->with([
                'service:id,name',
                'doctor:id,name',
            ])
            ->find($queueId);

        if (! $queue) {
            return response()->json(['message' => 'Queue not found or inactive.'], 404);
        }

        $servingToken = QueueToken::query()
            ->where('queue_id', $queue->id)
            ->where('status', QueueTokenStatus::Serving)
            ->with(['patient:id,name'])
            ->first();

        return response()->json([
            'queue_id' => $queue->id,
            'doctor_name' => $queue->doctor?->name,
            'service_name' => $queue->service?->name ?? 'Service',
            'current_flow_token' => $servingToken !== null ? (int) $servingToken->token_number : null,
            'patient_name' => $servingToken?->patient?->name,
            'remaining_count' => $this->waitingCount($queue->id),
        ]);
    }

    private function waitingCount(int $queueId): int
    {
        return (int) QueueToken::query()
            ->where('queue_id', $queueId)
            ->where('status', QueueTokenStatus::Waiting)
            ->count();
    }
}
