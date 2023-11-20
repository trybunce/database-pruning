<?php

namespace Bunce\PruneDatabase\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PruningCompletedEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly int $totalDeleted)
    {
    }
}
