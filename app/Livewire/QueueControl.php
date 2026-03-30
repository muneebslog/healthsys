<?php

namespace App\Livewire;

use App\Enums\QueueTokenStatus;
use App\Enums\UserRole;
use App\Models\Queue;
use App\Models\QueueToken;
use App\Services\QueueCallingService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use RuntimeException;

class QueueControl extends Component
{
    public Queue $queue;

    public string $activeTab = 'waiting';

    public function mount(Queue $queue): void
    {
        $role = Auth::user()->role;

        if (! config('hms.skip_role_page_guards') && ! in_array($role, [UserRole::Staff, UserRole::Admin], true)) {
            abort(403);
        }

        if (! $queue->isActive()) {
            abort(404);
        }

        $this->queue = $queue;
    }

    public function syncQueueFromServer(): void
    {
        $fresh = Queue::query()->with(['service', 'doctor'])->find($this->queue->id);

        if (! $fresh || ! $fresh->isActive()) {
            $this->redirect(route('queues.index'), navigate: true);

            return;
        }

        $this->queue = $fresh;
        unset($this->servingToken, $this->waitingTokens, $this->allTokens, $this->skippedTokens, $this->skippedCount, $this->doneCount);
    }

    public function setTab(string $tab): void
    {
        if (! in_array($tab, ['waiting', 'all', 'skipped'], true)) {
            return;
        }

        $this->activeTab = $tab;
    }

    public function callNext(QueueCallingService $queueCalling): void
    {
        $this->resetErrorBag();

        try {
            $queueCalling->callNext($this->queue);
        } catch (RuntimeException $e) {
            $this->addError('control', $e->getMessage());

            return;
        }

        $this->bumpQueue();
    }

    public function skip(QueueCallingService $queueCalling): void
    {
        $this->resetErrorBag();

        try {
            $queueCalling->skip($this->queue);
        } catch (RuntimeException $e) {
            $this->addError('control', $e->getMessage());

            return;
        }

        $this->bumpQueue();
    }

    public function previous(QueueCallingService $queueCalling): void
    {
        $this->resetErrorBag();

        try {
            $queueCalling->previous($this->queue);
        } catch (RuntimeException $e) {
            $this->addError('control', $e->getMessage());

            return;
        }

        $this->bumpQueue();
    }

    public function requeue(int $tokenId, QueueCallingService $queueCalling): void
    {
        $this->resetErrorBag();

        $token = QueueToken::query()
            ->where('queue_id', $this->queue->id)
            ->whereKey($tokenId)
            ->first();

        if (! $token) {
            $this->addError('control', __('Token not found in this queue.'));

            return;
        }

        try {
            $queueCalling->requeue($token);
        } catch (RuntimeException $e) {
            $this->addError('control', $e->getMessage());

            return;
        }

        $this->bumpQueue();
    }

    #[Computed]
    public function servingToken(): ?QueueToken
    {
        return QueueToken::query()
            ->where('queue_id', $this->queue->id)
            ->where('status', QueueTokenStatus::Serving)
            ->with(['patient:id,name'])
            ->first();
    }

    #[Computed]
    public function waitingTokens()
    {
        return QueueToken::query()
            ->where('queue_id', $this->queue->id)
            ->where('status', QueueTokenStatus::Waiting)
            ->with(['patient:id,name'])
            ->orderBy('token_number')
            ->get();
    }

    #[Computed]
    public function allTokens()
    {
        return QueueToken::query()
            ->where('queue_id', $this->queue->id)
            ->with(['patient:id,name'])
            ->orderBy('token_number')
            ->orderBy('id')
            ->get();
    }

    #[Computed]
    public function skippedTokens()
    {
        return QueueToken::query()
            ->where('queue_id', $this->queue->id)
            ->where('status', QueueTokenStatus::Skipped)
            ->with(['patient:id,name'])
            ->orderBy('token_number')
            ->get();
    }

    #[Computed]
    public function skippedCount(): int
    {
        return (int) QueueToken::query()
            ->where('queue_id', $this->queue->id)
            ->where('status', QueueTokenStatus::Skipped)
            ->count();
    }

    #[Computed]
    public function doneCount(): int
    {
        return (int) QueueToken::query()
            ->where('queue_id', $this->queue->id)
            ->where('status', QueueTokenStatus::Done)
            ->count();
    }

    public function render(): View
    {
        $this->queue->loadMissing(['service', 'doctor']);

        $titleParts = array_filter([
            $this->queue->doctor?->name,
            $this->queue->service?->name,
        ]);

        return view('livewire.queue-control')
            ->layout('layouts.app', [
                'title' => __('Queue control').(count($titleParts) ? ' — '.implode(' · ', $titleParts) : ''),
            ]);
    }

    public function formatArrivedAt(QueueToken $token): string
    {
        if ($token->status === QueueTokenStatus::Reserved) {
            return '—';
        }

        $moment = $token->called_at
            ?? ($token->status === QueueTokenStatus::Waiting ? $token->created_at : null)
            ?? $token->updated_at;

        return $moment
            ? $moment->timezone(config('app.timezone'))->format('g:i A')
            : '—';
    }

    private function bumpQueue(): void
    {
        unset($this->servingToken, $this->waitingTokens, $this->allTokens, $this->skippedTokens, $this->skippedCount, $this->doneCount);
        $this->queue->refresh();
    }
}
