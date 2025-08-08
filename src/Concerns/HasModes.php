<?php

namespace Datalogix\Fortress\Concerns;

use Datalogix\Fortress\Http\Middleware\Authenticate;
use Datalogix\Fortress\Http\Middleware\DispatchServingFortressEvent;

trait HasModes
{
    public function basic(string $id = 'default'): static
    {
        return $this
            ->id($id)
            ->default($id === 'default')
            ->login()
            ->logout()
            ->middleware(['web', DispatchServingFortressEvent::class])
            ->authMiddleware([Authenticate::class]);
    }

    public function admin(): static
    {
        return $this->basic()
            ->passwordReset()
            ->prefix('/admin');
    }

    public function product(): static
    {
        return $this->basic()
            ->passwordReset()
            ->registration()
            ->emailVerification();
    }
}
