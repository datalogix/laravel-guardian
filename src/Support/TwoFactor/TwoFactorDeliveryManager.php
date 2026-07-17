<?php

namespace Datalogix\Guardian\Support\TwoFactor;

use Closure;
use Datalogix\Guardian\Enums\TwoFactorMethod;
use Datalogix\Guardian\Exceptions\TwoFactorDeliveryException;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Mail\TwoFactorCodeMail;
use Datalogix\Guardian\Notifications\TwoFactorCodeNotification;
use Datalogix\Guardian\Notifications\TwoFactorSmsCodeNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

class TwoFactorDeliveryManager
{
    public function dispatch(
        Fortress $fortress,
        Model $user,
        TwoFactorMethod $method,
        string $code,
        string $context,
        ?Closure $sendEmailCodeUsing = null,
        ?Closure $sendSmsCodeUsing = null,
        ?Closure $resolveSmsRecipientUsing = null,
    ): void {
        if ($method === TwoFactorMethod::Totp) {
            return;
        }

        if ($method === TwoFactorMethod::Email) {
            $email = $this->resolveEmailRecipient($user);

            if (! is_string($email) || blank($email)) {
                throw TwoFactorDeliveryException::missingRecipient($method);
            }

            if ($sendEmailCodeUsing instanceof Closure) {
                $sendEmailCodeUsing($user, $code, $context, $fortress, $email);

                return;
            }

            if (method_exists($user, 'notify')) {
                $user->notify(new TwoFactorCodeNotification($code, $context));

                return;
            }

            Mail::to($email)->send(new TwoFactorCodeMail($code, $context));

            return;
        }

        if ($method === TwoFactorMethod::Sms) {
            $recipient = $this->resolveSmsRecipient($user, $fortress, $resolveSmsRecipientUsing);

            if (! is_string($recipient) || blank($recipient)) {
                throw TwoFactorDeliveryException::missingRecipient($method);
            }

            if ($sendSmsCodeUsing instanceof Closure) {
                $sendSmsCodeUsing($user, $recipient, $code, $context, $fortress);

                return;
            }

            if (class_exists('Illuminate\\Notifications\\Messages\\VonageMessage')) {
                Notification::route('vonage', $recipient)
                    ->notify(new TwoFactorSmsCodeNotification($code, $context));

                return;
            }

            throw TwoFactorDeliveryException::unavailableMethod($method);
        }

        throw TwoFactorDeliveryException::unavailableMethod($method);
    }

    protected function resolveEmailRecipient(Model $user): ?string
    {
        if (method_exists($user, 'getEmailForVerification')) {
            $email = $user->getEmailForVerification();

            if (is_string($email) && filled($email)) {
                return $email;
            }
        }

        $value = $user->getAttribute('email');

        return is_string($value) && filled($value) ? $value : null;
    }

    protected function resolveSmsRecipient(Model $user, Fortress $fortress, ?Closure $resolveSmsRecipientUsing = null): ?string
    {
        if ($resolveSmsRecipientUsing instanceof Closure) {
            $resolved = $resolveSmsRecipientUsing($user, $fortress);

            return is_string($resolved) && filled($resolved) ? $resolved : null;
        }

        foreach (['phone_number', 'phone'] as $attribute) {
            $value = $user->getAttribute($attribute);

            if (is_string($value) && filled($value)) {
                return $value;
            }
        }

        return null;
    }
}
