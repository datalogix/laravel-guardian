<?php

namespace Datalogix\Guardian\Notifications\Concerns;

trait QueuesTwoFactorCode
{
    /**
     * The code is rendered by a queue worker, which neither speaks the language of
     * the request nor should wait behind slower jobs while the code expires.
     */
    protected function queueTwoFactorCode(): void
    {
        $this->locale(app()->getLocale());
        $this->onConnection(config('guardian.two_factor_codes.connection'));
        $this->onQueue(config('guardian.two_factor_codes.queue'));
    }
}
