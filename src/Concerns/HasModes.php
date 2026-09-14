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

    public function admin(string $id = 'admin'): static
    {
        return $this->basic($id)->path('admin');
    }

    public function product(string $id = 'product'): static
    {
        return $this->basic($id)
            ->signUp()
            ->emailVerification();
    }
}
