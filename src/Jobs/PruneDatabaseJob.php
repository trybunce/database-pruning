<?php

namespace Bunce\PruneDatabase\Jobs;

use Bunce\PruneDatabase\ValueObjects\PruningConfig;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Laravel\SerializableClosure\Exceptions\PhpVersionNotSupportedException;

class PruneDatabaseJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(protected readonly PruningConfig $config)
    {
    }

    /**
     * Execute the job.
     *
     * @throws PhpVersionNotSupportedException
     */
    public function __invoke(): void
    {
        if ($this->batch()->cancelled() || ! $this->config->lock()->get()) {
            return;
        }

        $rowsDeleted = $this->prune();

        $this->config->lock()->forceRelease();

        $this->config->rowsDeletedInThisPass($rowsDeleted);

        $this->config->shouldContinuePruning()
            ? $this->continuePruning() : $this->sendAfterPruningEvents();
    }

    protected function prune(): int
    {
        return DB::transaction(fn () => $this->config->runDeleteQuery(), 5);
    }

    protected function continuePruning(): void
    {
        $this->config->incrementPass();

        $this->batch()->add([new static($this->config)]);
    }

    protected function sendAfterPruningEvents(): void
    {
        collect($this->config->afterPruningEvents)
            ->merge(config('prune-database.after-pruning-events', []))
            ->map(function (string $event) {
                if (class_exists($event)) {
                    event($event, ['totalDeleted' => $this->config->totalRowsDeleted]);
                }
            });
    }

    public function displayName(): string
    {
        return $this->config->displayName ?? static::class;
    }
}
