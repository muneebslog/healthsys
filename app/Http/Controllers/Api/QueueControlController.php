<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Queue;
use App\Models\QueueToken;
use App\Services\QueueCallingService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class QueueControlController extends Controller
{
    public function __construct(
        private QueueCallingService $queueCalling,
    ) {}

    public function callNext(Queue $queue): JsonResponse
    {
        if (! $queue->isActive()) {
            return response()->json(['message' => 'Queue is not active.'], 404);
        }

        try {
            $this->queueCalling->callNext($queue);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true]);
    }

    public function skip(Queue $queue): JsonResponse
    {
        if (! $queue->isActive()) {
            return response()->json(['message' => 'Queue is not active.'], 404);
        }

        try {
            $this->queueCalling->skip($queue);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true]);
    }

    public function previous(Queue $queue): JsonResponse
    {
        if (! $queue->isActive()) {
            return response()->json(['message' => 'Queue is not active.'], 404);
        }

        try {
            $this->queueCalling->previous($queue);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true]);
    }

    public function requeue(QueueToken $token): JsonResponse
    {
        try {
            $this->queueCalling->requeue($token);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true]);
    }
}
