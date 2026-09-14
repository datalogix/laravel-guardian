<?php

namespace Datalogix\Guardian\Support\TwoFactor;

use Closure;
use Datalogix\Guardian\Enums\TwoFactorMethod;
use Datalogix\Guardian\Exceptions\TwoFactorDeliveryException;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Notifications\TwoFactorCodeNotification;
use Datalogix\Guardian\Notifications\TwoFactorSmsCodeNotification;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Notification;

class TwoFactorDeliveryManager
{
    public function dispatch(
        Fortress $fortress,
        Authenticatable $user,
        TwoFactorMethod $method,
        string $code,
        string $context,
        ?Closure $sendEmailCodeUsing = null,
        ?Closure $sendSmsCodeUsing = null,
        ?Closure $resolveSmsRecipientUsing = null,
    ): void {
        match ($method) {
            TwoFactorMethod::Totp => null,
            TwoFactorMethod::Email => $this->dispatchEmail($fortress, $user, $code, $context, $sendEmailCodeUsing),
            TwoFactorMethod::Sms => $this->dispatchSms($fortress, $user, $code, $context, $sendSmsCodeUsing, $resolveSmsRecipientUsing),
        };
    }

    protected function dispatchEmail(Fortress $fortress, Authenticatable $user, string $code, string $context, ?Closure $sendEmailCodeUsing): void
    {
        $email = $this->resolveEmailRecipient($user);

        if (! is_string($email) || blank($email)) {
            throw TwoFactorDeliveryException::missingRecipient(TwoFactorMethod::Email);
        }

        if ($sendEmailCodeUsing instanceof Closure) {
            $sendEmailCodeUsing($user, $code, $context, $fortress, $email);

            return;
        }

        Notification::route('mail', $email)
            ->notify(new TwoFactorCodeNotification($code, $context));
    }

    protected function dispatchSms(Fortress $fortress, Authenticatable $user, string $code, string $context, ?Closure $sendSmsCodeUsing, ?Closure $resolveSmsRecipientUsing): void
    {
        $recipient = $this->resolveSmsRecipient($user, $fortress, $resolveSmsRecipientUsing);

        if (! is_string($recipient) || blank($recipient)) {
            throw TwoFactorDeliveryException::missingRecipient(TwoFactorMethod::Sms);
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

        throw TwoFactorDeliveryException::unavailableMethod(TwoFactorMethod::Sms);
    }

    protected function resolveEmailRecipient(Authenticatable $user): ?string
    {
        if (method_exists($user, 'getEmailForVerification')) {
            $email = $user->getEmailForVerification();

            if (is_string($email) && filled($email)) {
                return $email;
            }
        }

        $value = $user->email ?? null;

        return is_string($value) && filled($value) ? $value : null;
    }

    protected function resolveSmsRecipient(Authenticatable $user, Fortress $fortress, ?Closure $resolveSmsRecipientUsing = null): ?string
    {
        if ($resolveSmsRecipientUsing instanceof Closure) {
            $resolved = $resolveSmsRecipientUsing($user, $fortress);

            return is_string($resolved) && filled($resolved) ? $resolved : null;
        }

        foreach (['phone_number', 'phone'] as $attribute) {
            $value = $user->{$attribute} ?? null;

            if (is_string($value) && filled($value)) {
                return $value;
            }
        }

        return null;
    }
}
