<?php

namespace Datalogix\Guardian\Events;

use Datalogix\Guardian\Fortress;
use Exception;
use Illuminate\Foundation\Events\Dispatchable;

class FortressBootFailed
{
    use Dispatchable;

    public function __construct(
        public readonly Fortress $fortress,
        public readonly Exception $exception,
    ) {}
}
