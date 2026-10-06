<?php

namespace Datalogix\Guardian\Notifications\Concerns;

trait QueuesTwoFactorCode
{
    protected function queueTwoFactorCode(): void
    {
        $this->locale(app()->getLocale());
        $this->onConnection(config('guardian.two_factor_codes.connection'));
        $this->onQueue(config('guardian.two_factor_codes.queue'));
    }
}
