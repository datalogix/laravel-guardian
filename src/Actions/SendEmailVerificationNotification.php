<?php

namespace Datalogix\Guardian\Actions;

use Datalogix\Guardian\Guardian;
use Exception;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Model;

class SendEmailVerificationNotification
{
    use Concerns\HasRateLimiter;

    public function __invoke(Model $user)
    {
        return $this->throttleAction(function () use ($user) {
            if (! Guardian::hasEmailVerification()) {
                return;
            }

            if (! $user instanceof MustVerifyEmail) {
                return;
            }

            if ($user->hasVerifiedEmail()) {
                return;
            }

            if (! method_exists($user, 'notify')) {
                $userClass = $user::class;

                throw new Exception("Model [{$userClass}] does not have a [notify()] method.");
            }

            VerifyEmail::createUrlUsing(fn () => Guardian::getVerifyEmailUrl($user));

            $user->notify(app(VerifyEmail::class));
        }, $user->getKey(), Guardian::getEmailVerificationMaxAttempts());
    }
}
