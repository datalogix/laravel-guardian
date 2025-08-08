<?php

namespace Datalogix\Fortress\Pages\Auth;

use Datalogix\Fortress\Facades\Fortress;
use Datalogix\Fortress\Http\Responses\Auth\Contracts\PasswordResetResponse;
use Datalogix\Fortress\Pages\Page;
use Illuminate\Auth\Events\PasswordReset as PasswordResetEvent;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Livewire\Attributes\Locked;

class PasswordReset extends Page
{
    #[Locked]
    public string $token = '';

    public string $email = '';

    public string $password = '';

    public string $passwordConfirmation = '';

    public function mount(?string $token = null, ?string $email = null): void
    {
        $this->token = $token ?? request()->string('token');
        $this->email = $email ?? request()->string('email');
    }

    public function submit(): ?PasswordResetResponse
    {
        $data = $this->getData();
        $hasPanelAccess = true;

        $status = Password::broker(Fortress::getAuthPasswordBroker())->reset(
            $data,
            function (CanResetPassword|Model|Authenticatable $user, string $password) use (&$hasPanelAccess) {
                if (Fortress::cannotAccess($user)) {
                    $hasPanelAccess = false;

                    return;
                }

                $user->forceFill([
                    'password' => Hash::make($password),
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordResetEvent($user));
            }
        );

        if ($hasPanelAccess === false) {
            $status = Password::INVALID_USER;
        }

        if ($status === Password::PASSWORD_RESET) {
            return app(PasswordResetResponse::class);
        }

        return null;
    }

    protected function getData(): array
    {
        return $this->validate([
            'token' => ['required'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', PasswordRule::default(), 'confirmed'],
        ]);
    }
}
