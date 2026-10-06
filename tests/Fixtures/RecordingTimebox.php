<?php

namespace Datalogix\Guardian\Tests\Fixtures;

use Illuminate\Support\Timebox;

/**
 * Records the durations it was asked to enforce, and whether each call answered
 * early, without waiting for them.
 */
class RecordingTimebox extends Timebox
{
    /**
     * @var array<int, int>
     */
    public array $durations = [];

    /**
     * @var array<int, bool>
     */
    public array $returnedEarly = [];

    public function call(callable $callback, int $microseconds)
    {
        $this->durations[] = $microseconds;
        $this->earlyReturn = false;

        $result = $callback($this);

        $this->returnedEarly[] = $this->earlyReturn;

        return $result;
    }
}
