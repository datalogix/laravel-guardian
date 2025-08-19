<?php

namespace Datalogix\Guardian\Actions;

use Datalogix\Guardian\Enums\IdentifierKey;
use Datalogix\Guardian\Exceptions\LoginException;
use Datalogix\Guardian\Guardian;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rules\Password;

class Login
{
    public function __invoke(array $data = [], bool $remember = true)
    {
        $throttleKey = sha1(static::class.'|'.request()->ip());
        $this->ensureIsNotRateLimited($throttleKey);

        $credentials = $this->parseCredentials($data);
        $auth = Guardian::auth();

        if (! $auth->attempt($credentials, $remember)) {
            RateLimiter::hit($throttleKey);

            throw LoginException::invalid();
        }

        $user = $auth->user();

        if (Guardian::cannotAccess($user)) {
            RateLimiter::hit($throttleKey);

            $auth->logout();

            throw LoginException::cannotAccess($auth);
        }

        RateLimiter::clear($throttleKey);
        Session::regenerate();

        return Guardian::redirect(intended: true);
    }

    protected function parseCredentials(array $data = []): array
    {
        $identifierKey = match (Guardian::getIdentifierKey()) {
            IdentifierKey::Email => 'email',
            IdentifierKey::Username => 'username',
            IdentifierKey::Both => filter_var($data['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username',
        };

        return [
            $identifierKey => $data['login'],
            'password' => $data['password'],
        ];
    }

    public static function rules(): array
    {
        return [
            'login' => match (Guardian::getIdentifierKey()) {
                IdentifierKey::Email => ['required', 'string', 'email', 'max:255'],
                IdentifierKey::Username => ['required', 'string', 'min:5', 'max:20', 'lowercase', 'alpha_num'],
                IdentifierKey::Both => ['required', 'string', 'max:255'],
            },
            'password' => ['required', 'string', Password::default()],
        ];
    }

    protected function ensureIsNotRateLimited(string $throttleKey)
    {
        $maxAttempts = Guardian::getLoginMaxAttempts() ?? 5;

        if ($maxAttempts === false) {
            return;
        }

        if (! RateLimiter::tooManyAttempts($throttleKey, $maxAttempts)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($throttleKey);

        throw LoginException::rateLimited($seconds);
    }
}
