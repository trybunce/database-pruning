<?php

namespace Bunce\PruneDatabase\Concerns;

use Bunce\PruneDatabase\Jobs\PruneDatabaseJob;
use Bunce\PruneDatabase\ValueObjects\PruningConfig;
use Closure;
use Illuminate\Bus\Batch;
use Illuminate\Bus\PendingBatch;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Bus;
use InvalidArgumentException;

class DatabasePruningFactory
{
    public PruningConfig $pruningConfig;

    public ?int $chunk = null;

    public string|PruneDatabaseJob $job = PruneDatabaseJob::class;

    public function __construct(protected Builder|\Illuminate\Database\Eloquent\Builder|null $query = null)
    {
        $this->pruningConfig = new PruningConfig();
    }

    public static function new(): static
    {
        return new static();
    }

    public static function forQuery(Builder|\Illuminate\Database\Eloquent\Builder $query): static
    {
        return new static($query);
    }

    public function chunk(int $size): static
    {
        $this->chunk = $size;

        return $this;
    }

    public function displayName(string $displayName): static
    {
        $this->pruningConfig->displayName($displayName);

        return $this;
    }

    protected function getJob(): PruneDatabaseJob
    {
        $this->ensureQueryAndChunkIsSet();

        $this->pruningConfig->using($this->query, $this->chunk);

        return new $this->job($this->pruningConfig);
    }

    public function getBatch(): PendingBatch
    {
        return Bus::batch([$this->getJob()])->allowFailures();
    }

    /**
     * @throws \Throwable
     */
    public function dispatch(): Batch
    {
        return $this->getBatch()->dispatch();
    }

    public function usingConnection(string $connection): static
    {
        $this->pruningConfig->connection = $connection;

        return $this;
    }

    public function stopWhen(Closure $closure): static
    {
        $this->pruningConfig->stopWhen($closure);

        return $this;
    }

    public function afterPruningEvents(string|array $events): static
    {
        $this->pruningConfig->afterPruningEvents($events);

        return $this;
    }

    protected function ensureQueryAndChunkIsSet(): void
    {
        if (is_null($this->query)) {
            throw new InvalidArgumentException('Query property missing');
        }

        if (is_null($this->chunk)) {
            throw new InvalidArgumentException('Chunk property missing');
        }
    }
}
