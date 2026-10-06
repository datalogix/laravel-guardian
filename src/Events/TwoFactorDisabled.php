<?php

namespace Datalogix\Guardian\Events;

use Datalogix\Guardian\Fortress;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

class TwoFactorDisabled
{
    use Dispatchable;

    public function __construct(
        public readonly Fortress $fortress,
        public readonly Model $user,
    ) {}
}
