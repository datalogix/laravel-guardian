<?php

namespace Datalogix\Guardian\Concerns;

use Datalogix\Guardian\Http\Middleware\Authenticate;
use Datalogix\Guardian\Http\Middleware\AuthenticateSession;
use Datalogix\Guardian\Http\Middleware\DispatchServingGuardianEvent;

trait HasModes
{
    public function basic(?string $id = null): static
    {
        $id === null ? $this->presetId('default') : $this->id($id);

        return $this
            // Lazy: id() may still change after the preset.
            ->default(fn () => $this->getId() === 'default')
            ->login()
            ->logout()
            ->passwordReset()
            ->passwordConfirmation()
            ->middleware([DispatchServingGuardianEvent::class])
            ->authMiddleware([Authenticate::class, AuthenticateSession::class]);
    }

    public function admin(?string $id = null): static
    {
        $id === null ? $this->presetId('admin') : $this->id($id);

        return $this->basic()->path('admin');
    }

    public function product(?string $id = null): static
    {
        $id === null ? $this->presetId('product') : $this->id($id);

        return $this->basic()
            ->signUp()
            ->emailVerification();
    }
}
