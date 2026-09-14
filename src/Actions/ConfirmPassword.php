<?php

namespace Datalogix\Guardian\Actions;

use Datalogix\Guardian\Actions\Contracts\HasValidationRules;
use Datalogix\Guardian\Exceptions\PasswordConfirmationException;
use Datalogix\Guardian\Guardian;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Timebox;
use Illuminate\Validation\Rules\Password as PasswordRule;

class ConfirmPassword implements HasValidationRules
{
    use Concerns\HasRateLimiter;

    public function __invoke(array $data = []): void
    {
        $auth = Guardian::auth();
        $user = $auth->user();

        if (! $user) {
            throw PasswordConfirmationException::invalid();
        }

        $maxAttempts = Guardian::getPasswordConfirmationFeature()->getMaxAttempts();
        $throttleKey = $this->throttleKey($auth->id(), includeIp: false);

        $this->ensureIsNotRateLimited(
            $throttleKey,
            $maxAttempts,
            fn (int $seconds) => throw PasswordConfirmationException::rateLimited($seconds)
        );

        $identifierKey = Guardian::getIdentifierKey();

        $credentials = [
            ...$data,
            ...[$identifierKey->value => data_get($user, $identifierKey->value)],
        ];

        if (! $this->timeboxedValidate($auth, $credentials)) {
            $this->hitRateLimiterIfThrottled($throttleKey, $maxAttempts);

            throw PasswordConfirmationException::invalid();
        }

        $this->clearRateLimiterIfThrottled($throttleKey, $maxAttempts);

        Session::put('auth.password_confirmed_at', time());
    }

    protected function timeboxedValidate($auth, array $credentials): bool
    {
        return app(Timebox::class)->call(function ($timebox) use ($auth, $credentials) {
            $valid = $auth->validate($credentials);

            $timebox->returnEarly();

            return $valid;
        }, config('auth.timebox_duration', 200000));
    }

    public static function rules(): array
    {
        return [
            'password' => ['required', 'string', PasswordRule::default()],
        ];
    }
}
