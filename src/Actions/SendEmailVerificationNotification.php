<?php

namespace Datalogix\Guardian\Actions;

use Datalogix\Guardian\Actions\Concerns\HasRateLimiter;
use Datalogix\Guardian\Exceptions\GuardianException;
use Datalogix\Guardian\Guardian;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Model;

class SendEmailVerificationNotification
{
    use HasRateLimiter;

    public function __invoke(Model $user): bool
    {
        return $this->throttleAction(function () use ($user) {
            if (! Guardian::getEmailVerificationVerifyFeature()->hasFeature()) {
                return false;
            }

            if (! $user instanceof MustVerifyEmail) {
                return false;
            }

            if ($user->hasVerifiedEmail()) {
                return false;
            }

            if (! method_exists($user, 'notify')) {
                throw new GuardianException('The ['.$user::class.'] model must use the Notifiable trait to receive email verification notifications.');
            }

            VerifyEmail::createUrlUsing(fn (mixed $notifiable) => Guardian::getVerifyEmailUrl($notifiable));
            $user->sendEmailVerificationNotification();

            return true;
        }, fn () => false, $user->getKey(), Guardian::getEmailVerificationPromptFeature()->getMaxAttempts());
    }
}
