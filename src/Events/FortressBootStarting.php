<?php

namespace Datalogix\Guardian\Events;

use Datalogix\Guardian\Fortress;
use Illuminate\Foundation\Events\Dispatchable;

class FortressBootStarting
{
    use Dispatchable;

    public function __construct(
        public readonly Fortress $fortress,
    ) {}
}
