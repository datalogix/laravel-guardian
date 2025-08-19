<?php

namespace Datalogix\Guardian\Concerns;

use Datalogix\Guardian\Http\Middleware\Authenticate;
use Datalogix\Guardian\Http\Middleware\DispatchServingGuardianEvent;

trait HasModes
{
    public function basic(string $id = 'default'): static
    {
        return $this
            ->id($id)
            ->default($id === 'default')
            ->login()
            ->logout()
            ->passwordReset()
            ->passwordConfirmation()
            ->middleware(['web', DispatchServingGuardianEvent::class])
            ->authMiddleware([Authenticate::class]);
    }

    public function admin(): static
    {
        return $this->basic()->path('admin');
    }

    public function product(): static
    {
        return $this->basic()
            ->signUp()
            ->emailVerification();
    }
}
