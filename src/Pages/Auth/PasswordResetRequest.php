<?php

namespace Datalogix\Fortress\Pages\Auth;

use Datalogix\Fortress\Facades\Fortress;
use Datalogix\Fortress\Pages\Page;
use Exception;
use Illuminate\Auth\Events\PasswordResetLinkSent;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Support\Facades\Password;

class PasswordResetRequest extends Page
{
    public ?string $username = null;

    public function submit(): void
    {
        $data = $this->getData();

        $status = Password::broker(Fortress::getAuthPasswordBroker())->sendResetLink(
            $data,
            function (CanResetPassword $user, string $token) {
                if (Fortress::cannotAccess($user)) {
                    return;
                }

                if (! method_exists($user, 'notify')) {
                    $userClass = $user::class;

                    throw new Exception("Model [{$userClass}] does not have a [notify()] method.");
                }

                ResetPasswordNotification::createUrlUsing(fn () => Fortress::getResetPasswordUrl($token, $user));

                $user->notify(app(ResetPasswordNotification::class, ['token' => $token]));

                event(new PasswordResetLinkSent($user));
            },
        );

        if ($status !== Password::RESET_LINK_SENT) {
            return;
        }

        session()->flash('status', __('passwords.sent'));
    }

    protected function getData(): array
    {
        return $this->validate([
            'username' => 'required',
        ]);
    }
}
