<?php

namespace Datalogix\Guardian\Actions;

use Datalogix\Guardian\Guardian;
use Exception;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\RateLimiter;

class SendEmailVerificationNotification
{
    public function __invoke(Model $user)
    {
        return RateLimiter::attempt(sha1(static::class.'|'.request()->ip()), 2, function () use ($user) {
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
        });
    }
}
