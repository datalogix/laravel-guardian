<?php

namespace Datalogix\Guardian\Actions;

use Datalogix\Guardian\Actions\Contracts\HasValidationRules;
use Datalogix\Guardian\Enums\AuthFlowResult;
use Datalogix\Guardian\Exceptions\LoginException;
use Datalogix\Guardian\Exceptions\UnsupportedAuthGuardException;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Support\Auth\PostAuthenticationFlow;
use Illuminate\Auth\Events\Attempting;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Validated;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Timebox;

class Login implements HasValidationRules
{
    use Concerns\HasRateLimiter;

    public function __construct(
        protected PostAuthenticationFlow $postAuthenticationFlow,
    ) {
        //
    }

    public function __invoke(array $data = [], bool $remember = true): AuthFlowResult
    {
        $maxAttempts = Guardian::getLoginFeature()->getMaxAttempts();
        $throttleKey = $this->throttleKey(Str::lower($data['login'] ?? ''));

        $this->ensureIsNotRateLimited(
            $throttleKey,
            $maxAttempts,
            fn (int $seconds) => throw LoginException::rateLimited($seconds)
        );

        $credentials = $this->parseCredentials($data);
        $guardName = Guardian::getGuard();

        event(new Attempting($guardName, $credentials, $remember));

        $user = $this->timeboxedAttempt($credentials, $guardName);

        if (! $user) {
            $this->hitRateLimiterIfThrottled($throttleKey, $maxAttempts);

            throw LoginException::invalid();
        }

        if ($user instanceof Model && Guardian::cannotAccess($user)) {
            $this->hitRateLimiterIfThrottled($throttleKey, $maxAttempts);

            throw LoginException::cannotAccess();
        }

        $this->clearRateLimiterIfThrottled($throttleKey, $maxAttempts);

        return $this->postAuthenticationFlow->handle($user, $remember);
    }

    protected function timeboxedAttempt(array $credentials, string $guardName): ?Authenticatable
    {
        return app(Timebox::class)->call(function ($timebox) use ($credentials, $guardName) {
            $user = $this->retrieveUser($credentials);

            if (! $user || ! $this->credentialsAreValid($user, $credentials)) {
                event(new Failed($guardName, $user, $credentials));

                return null;
            }

            $this->rehashPasswordIfRequired($user, $credentials);

            event(new Validated($guardName, $user));

            $timebox->returnEarly();

            return $user;
        }, config('auth.timebox_duration', 200000));
    }

    protected function parseCredentials(array $data = []): array
    {
        return [
            Guardian::getIdentifierKey()->value => $data['login'],
            'password' => $data['password'],
        ];
    }

    public static function rules(): array
    {
        return [
            'login' => Guardian::getIdentifierKey()->rules(),
            'password' => ['required', 'string'],
        ];
    }

    protected function retrieveUser(array $credentials): ?Authenticatable
    {
        try {
            return Guardian::authProvider()->retrieveByCredentials($credentials);
        } catch (UnsupportedAuthGuardException) {
            return null;
        }
    }

    protected function credentialsAreValid(Authenticatable $user, array $credentials): bool
    {
        try {
            return Guardian::authProvider()->validateCredentials($user, $credentials);
        } catch (UnsupportedAuthGuardException) {
            return false;
        }
    }

    protected function rehashPasswordIfRequired(Authenticatable $user, array $credentials): void
    {
        Guardian::authProvider()->rehashPasswordIfRequired($user, $credentials);
    }
}
