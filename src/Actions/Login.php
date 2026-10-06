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
use Illuminate\Support\Timebox;

class Login implements HasValidationRules
{
    use Concerns\HasRateLimiter;
    use Concerns\RemapsLoginField;

    public function __construct(
        protected PostAuthenticationFlow $postAuthenticationFlow,
    ) {}

    public function __invoke(array $data = [], bool $remember = true): AuthFlowResult
    {
        $maxAttempts = Guardian::getLoginFeature()->getMaxAttempts();
        $throttleKey = $this->throttleKey((string) Guardian::getIdentifierKey()->normalize($data['login'] ?? ''));

        $this->reserveAttempt(
            $throttleKey,
            $maxAttempts,
            fn (int $seconds) => throw LoginException::rateLimited($seconds)
        );

        $credentials = $this->remapLoginField(['login' => $data['login'], 'password' => $data['password']]);
        $guardName = Guardian::getGuard();

        event(new Attempting($guardName, $credentials, $remember));

        $user = $this->timeboxedAttempt($credentials, $guardName);

        if (! $user) {
            throw LoginException::invalid();
        }

        if ($user instanceof Model && Guardian::cannotAccess($user)) {
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
        return Guardian::authProvider()->validateCredentials($user, $credentials);
    }

    protected function rehashPasswordIfRequired(Authenticatable $user, array $credentials): void
    {
        Guardian::authProvider()->rehashPasswordIfRequired($user, $credentials);
    }
}
