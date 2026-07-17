<?php

namespace Datalogix\Guardian\Actions;

use Datalogix\Guardian\Guardian;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Model;

class SendEmailVerificationNotification
{
    use Concerns\HasRateLimiter;

    public function __invoke(Model $user)
    {
        return $this->throttleAction(function () use ($user) {
            if (! Guardian::getEmailVerificationVerifyFeature()->hasFeature()) {
                return;
            }

            if (! $user instanceof MustVerifyEmail) {
                return;
            }

            if ($user->hasVerifiedEmail()) {
                return;
            }

            VerifyEmail::createUrlUsing(fn (mixed $notifiable) => Guardian::getVerifyEmailUrl($notifiable));
            $user->sendEmailVerificationNotification();
        }, $user->getKey(), Guardian::getEmailVerificationVerifyFeature()->getMaxAttempts());
    }
}
