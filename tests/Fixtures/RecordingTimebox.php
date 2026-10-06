<?php

namespace Datalogix\Guardian\Tests\Fixtures;

use Illuminate\Support\Timebox;

/**
 * Records the durations it was asked to enforce, without waiting for them.
 */
class RecordingTimebox extends Timebox
{
    /**
     * @var array<int, int>
     */
    public array $durations = [];

    public function call(callable $callback, int $microseconds)
    {
        $this->durations[] = $microseconds;

        return $callback($this);
    }
}
