<?php

namespace Datalogix\Fortress\Actions\Auth;

use Datalogix\Fortress\Facades\Fortress;
use Exception;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Model;

class SendEmailVerificationNotification
{
    public function __invoke(Model $user): void
    {
        if (! Fortress::hasEmailVerification()) {
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

        VerifyEmail::createUrlUsing(fn () => Fortress::getVerifyEmailUrl($user));

        $user->notify(app(VerifyEmail::class));
    }
}
