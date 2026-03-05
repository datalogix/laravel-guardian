<?php

namespace Datalogix\Guardian\Actions;

use Datalogix\Guardian\Actions\Contracts\HasValidationRules;
use Datalogix\Guardian\Enums\IdentifierKey;
use Datalogix\Guardian\Exceptions\LoginException;
use Datalogix\Guardian\Guardian;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rules\Password;

class Login implements HasValidationRules
{
    use Concerns\HasRateLimiter;

    public function __invoke(array $data = [], bool $remember = true): bool
    {
        $throttleKey = $this->throttleKey();
        $this->ensureIsNotRateLimited($throttleKey);

        $credentials = $this->parseCredentials($data);
        $auth = Guardian::auth();
        $user = $this->retrieveUser($credentials);

        if (! $user || ! $this->credentialsAreValid($user, $credentials)) {
            RateLimiter::hit($throttleKey);

            throw LoginException::invalid();
        }

        if ($user instanceof Model && Guardian::cannotAccess($user)) {
            RateLimiter::hit($throttleKey);

            throw LoginException::cannotAccess($auth);
        }

        if ($user instanceof Model && Guardian::requiresTwoFactorChallenge($user)) {
            RateLimiter::clear($throttleKey);

            Guardian::startTwoFactorChallenge($user, $remember);

            return true;
        }

        $auth->login($user, $remember);
        Guardian::clearTwoFactorChallenge();

        RateLimiter::clear($throttleKey);
        Session::regenerate();

        return false;
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

    protected function ensureIsNotRateLimited(string $throttleKey): void
    {
        $maxAttempts = Guardian::getLoginFeature()->getMaxAttempts();

        if (! $this->shouldThrottle($maxAttempts)) {
            return;
        }

        if (! RateLimiter::tooManyAttempts($throttleKey, $maxAttempts)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($throttleKey);

        throw LoginException::rateLimited($seconds);
    }

    protected function retrieveUser(array $credentials): ?Authenticatable
    {
        $auth = Guardian::auth();

        if (! method_exists($auth, 'getProvider')) {
            return null;
        }

        return $auth->getProvider()->retrieveByCredentials($credentials);
    }

    protected function credentialsAreValid(Authenticatable $user, array $credentials): bool
    {
        $auth = Guardian::auth();

        if (! method_exists($auth, 'getProvider')) {
            return false;
        }

        return $auth->getProvider()->validateCredentials($user, $credentials);
    }
}
