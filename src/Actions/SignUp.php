<?php

namespace Datalogix\Guardian\Actions;

use Datalogix\Guardian\Enums\IdentifierKey;
use Datalogix\Guardian\Guardian;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\SessionGuard;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class SignUp
{
    protected static string $userModel;

    public function __invoke(array $data = [])
    {
        $user = static::getUserModel()::create($data);

        event(new Registered($user));

        app(SendEmailVerificationNotification::class)($user);

        Guardian::auth()->login($user, true);

        session()->regenerate();

        return Guardian::redirect(intended: true);
    }

    public static function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(static::getUserModel())],
            'password' => ['required', 'string', Password::default(), 'confirmed'],
            'password_confirmation' => ['required', 'string', Password::default()],
        ];

        if (Guardian::getIdentifierKey() !== IdentifierKey::Email) {
            $rules += ['username' => ['required', 'string', 'min:5', 'max:20', 'lowercase', 'alpha_num', Rule::unique(static::getUserModel())]];
        }

        return $rules;
    }

    protected static function getUserModel(): string
    {
        if (isset(static::$userModel)) {
            return static::$userModel;
        }

        /** @var SessionGuard $guard */
        $guard = Guardian::auth();

        /** @var EloquentUserProvider $provider */
        $provider = $guard->getProvider();

        return static::$userModel = $provider->getModel();
    }
}
