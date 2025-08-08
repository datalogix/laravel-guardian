<?php

namespace Datalogix\Fortress\Pages\Auth;

use Datalogix\Fortress\Facades\Fortress;
use Datalogix\Fortress\Http\Responses\Auth\Contracts\LoginResponse;
use Datalogix\Fortress\Pages\Page;
use Illuminate\Validation\ValidationException;

class Login extends Page
{
    public ?string $email = null;

    public ?string $password = null;

    public ?bool $remember = null;

    public function submit(): ?LoginResponse
    {
        $data = $this->getData();
        $auth = Fortress::auth();

        if (! $auth->attempt($data, $this->remember)) {
            $this->throwFailureValidationException();
        }

        $user = $auth->user();

        if (Fortress::cannotAccess($user)) {
            $auth->logout();

            $this->throwFailureValidationException();
        }

        session()->regenerate();

        return app(LoginResponse::class);
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'email' => __('auth.failed'),
        ]);
    }

    protected function getData(): array
    {
        return $this->validate([
            'email' => 'required',
            'password' => 'required',
        ]);
    }
}
