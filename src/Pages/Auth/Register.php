<?php

namespace Datalogix\Fortress\Pages\Auth;

use Datalogix\Fortress\Actions\Auth\SendEmailVerificationNotification;
use Datalogix\Fortress\Facades\Fortress;
use Datalogix\Fortress\Http\Responses\Auth\Contracts\RegistrationResponse;
use Datalogix\Fortress\Pages\Page;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\SessionGuard;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;

class Register extends Page
{
    public ?string $name = null;

    public ?string $email = null;

    public ?string $password = null;

    public ?string $password_confirmation = null;

    protected string $userModel;

    public function submit(): ?RegistrationResponse
    {
        $user = $this->getUserModel()::create($this->getData());

        event(new Registered($user));

        app(SendEmailVerificationNotification::class)($user);

        Fortress::auth()->login($user, true);

        session()->regenerate();

        return app(RegistrationResponse::class);
    }

    protected function getUserModel(): string
    {
        if (isset($this->userModel)) {
            return $this->userModel;
        }

        /** @var SessionGuard $authGuard */
        $authGuard = Fortress::auth();

        /** @var EloquentUserProvider $provider */
        $provider = $authGuard->getProvider();

        return $this->userModel = $provider->getModel();
    }

    protected function getData(): array
    {
        return $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique($this->getUserModel())],
            'password' => ['required', PasswordRule::default(), 'confirmed'],
            'password_confirmation' => ['required', PasswordRule::default()],
        ]);
    }
}
