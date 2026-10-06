<?php

namespace Datalogix\Guardian\Actions;

use Datalogix\Guardian\Actions\Concerns\HasRateLimiter;
use Datalogix\Guardian\Actions\Concerns\RemapsLoginField;
use Datalogix\Guardian\Actions\Contracts\HasValidationRules;
use Datalogix\Guardian\Exceptions\ResetPasswordException;
use Datalogix\Guardian\Guardian;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class ResetPassword implements HasValidationRules
{
    use HasRateLimiter;
    use RemapsLoginField;

    public function __invoke(array $data = []): string
    {
        $credentials = $this->remapLoginField($data);

        return $this->throttleAction(
            function () use ($credentials) {
                $status = $this->reset($credentials);

                if ($status !== Password::PASSWORD_RESET) {
                    throw ResetPasswordException::forStatus($status);
                }

                return $status;
            },
            fn (int $seconds) => throw ResetPasswordException::rateLimited($seconds),
            (string) Guardian::getIdentifierKey()->normalize($data['login'] ?? ''),
            Guardian::getResetPasswordFeature()->getMaxAttempts()
        );
    }

    protected function reset(array $credentials): string
    {
        $deniedAccess = false;

        $status = Password::broker(Guardian::getPasswordBroker())->reset(
            $credentials,
            function (CanResetPassword|Model|Authenticatable $user, string $password) use (&$deniedAccess) {
                if (! $user instanceof Model || Guardian::cannotAccess($user)) {
                    $deniedAccess = true;

                    return;
                }

                $user->forceFill(['password' => Hash::make($password)]);
                $user->setRememberToken(Str::random(60));
                $user->save();

                event(new PasswordReset($user));
            }
        );

        // Same answer as for an unknown user.
        return $deniedAccess ? Password::INVALID_USER : $status;
    }

    public static function rules(): array
    {
        return [
            'token' => ['required'],
            'login' => Guardian::getIdentifierKey()->rules(),
            'password' => ['required', 'string', PasswordRule::default(), 'confirmed'],
            'password_confirmation' => ['required', 'string', PasswordRule::default()],
        ];
    }
}
