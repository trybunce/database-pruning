<?php

namespace Bunce\PruneDatabase\ValueObjects;

use Carbon\CarbonInterval;
use Closure;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\SerializableClosure\Exceptions\PhpVersionNotSupportedException;
use Laravel\SerializableClosure\SerializableClosure;

class PruningConfig
{
    public string $lockCacheStore = 'redis';

    public int|CarbonInterval $releaseLockAfterSeconds;

    public ?string $connection = null;

    public string $sql;

    public array $sqlBindings;

    public int $deleteChunkSize = 1000;

    public ?string $displayName = null;

    public string $lockName = '';

    public int $pass = 1;

    public int $rowsDeletedInThisPass = 0;

    public string|Closure|null $stopWhen = null;

    public int $totalRowsDeleted = 0;

    public array $afterPruningEvents = [];

    public function __construct()
    {
        $this->lockCacheStore = 'redis';
        $this->releaseLockAfterSeconds = 60 * 20;
    }

    public function displayName(string $displayName): static
    {
        $this->displayName = $displayName;

        return $this;
    }

    public function runDeleteQuery(): int
    {
        return DB::connection($this->connection)->delete($this->sql, $this->sqlBindings);
    }

    public function using(Builder|\Illuminate\Database\Eloquent\Builder $query, int $chunkSize = 500): void
    {
        $baseQuery = $query instanceof \Illuminate\Database\Eloquent\Builder
            ? $query->toBase()
            : $query;

        $this->lockName = $this->queryToLockName($baseQuery);

        $this->sql = $baseQuery->limit($chunkSize)->getGrammar()->compileDelete($baseQuery);

        $this->sqlBindings = $baseQuery->getBindings();

        $this->deleteChunkSize = $chunkSize;

        if (is_null($this->stopWhen)) {
            $this->stopWhen(
                fn (PruningConfig $cleanConfig) => $cleanConfig->rowsDeletedInThisPass < $cleanConfig->deleteChunkSize
            );
        }
    }

    protected function queryToLockName(Builder|\Illuminate\Database\Eloquent\Builder $query): string
    {
        return md5($query->toSql().print_r($query->getBindings(), true));
    }

    /**
     * @throws PhpVersionNotSupportedException
     */
    public function stopWhen(Closure $closure): void
    {
        $wrapper = new SerializableClosure($closure);

        $this->stopWhen = serialize($wrapper);
    }

    /**
     * @throws PhpVersionNotSupportedException
     */
    public function shouldContinuePruning(): bool
    {
        $wrapper = unserialize($this->stopWhen);

        $stopWhen = $wrapper->getClosure();

        return ! $stopWhen($this);
    }

    public function rowsDeletedInThisPass(int $rowsDeleted): static
    {
        $this->rowsDeletedInThisPass = $rowsDeleted;

        $this->totalRowsDeleted += $rowsDeleted;

        return $this;
    }

    public function incrementPass(): static
    {
        $this->pass++;

        $this->rowsDeletedInThisPass = 0;

        return $this;
    }

    public function afterPruningEvents(string|array $events): static
    {
        $this->afterPruningEvents = array_merge($this->afterPruningEvents, Arr::wrap($events));

        return $this;
    }

    public function lock(): Lock
    {
        // @phpstan-ignore-next-line
        return Cache::store($this->lockCacheStore)->lock($this->lockName, $this->releaseLockAfterSeconds);
    }
}
