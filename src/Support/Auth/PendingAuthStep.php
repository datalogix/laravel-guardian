<?php

namespace Datalogix\Guardian\Support\Auth;

use Illuminate\Contracts\Auth\Authenticatable;

interface PendingAuthStep
{
    public function hasPendingState(): bool;

    public function resolveUser(): ?Authenticatable;

    public function isValid(): bool;

    public function clear(): void;

    public function authorize(?Authenticatable $user): void;
}
