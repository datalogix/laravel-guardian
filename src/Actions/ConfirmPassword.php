<?php

namespace Datalogix\Guardian\Actions;

use Datalogix\Guardian\Actions\Contracts\HasValidationRules;
use Datalogix\Guardian\Exceptions\PasswordConfirmationException;
use Datalogix\Guardian\Guardian;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Timebox;

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

        $this->reserveAttempt(
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
            throw PasswordConfirmationException::invalid();
        }

        $this->clearRateLimiterIfThrottled($throttleKey, $maxAttempts);

        Session::put('auth.password_confirmed_at', time());
    }

    protected function timeboxedValidate($auth, array $credentials): bool
    {
        return app(Timebox::class)->call(function ($timebox) use ($auth, $credentials) {
            $valid = $auth->validate($credentials);

            // Only a correct password returns early, so timing does not reveal a wrong one.
            if ($valid) {
                $timebox->returnEarly();
            }

            return $valid;
        }, config('auth.timebox_duration', 200000));
    }

    public static function rules(): array
    {
        return [
            // Not the rules of a new password: a stricter policy must not lock users out.
            'password' => ['required', 'string'],
        ];
    }
}
