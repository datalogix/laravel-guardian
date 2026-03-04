<?php

namespace Datalogix\Guardian\Events;

use Datalogix\Guardian\Fortress;
use Illuminate\Foundation\Events\Dispatchable;
use Throwable;

class FortressBootFailed
{
    use Dispatchable;

    public function __construct(
        public readonly Fortress $fortress,
        public readonly Throwable $exception,
    ) {}
}
