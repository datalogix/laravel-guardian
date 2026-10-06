<?php

namespace Datalogix\Guardian\Framework\Inertia\Controllers;

use Datalogix\Guardian\Actions\SendEmailVerificationNotification;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Response\Redirector;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;

class EmailVerificationPromptController extends PageController
{
    protected static function page(): string
    {
        return 'email-verification-prompt';
    }

    public function __invoke(Request $request)
    {
        $user = Guardian::user();

        if ($user instanceof MustVerifyEmail && $user->hasVerifiedEmail()) {
            return Redirector::redirectIntended();
        }

        return $this->render($request);
    }

    protected function props(Request $request): array
    {
        return [
            'logoutUrl' => Guardian::logoutUrl(),
        ];
    }

    public function submit(Request $request)
    {
        $sent = app(SendEmailVerificationNotification::class)(Guardian::user());

        return $this->respond(
            app(Guardian::getEmailVerificationPromptFeature()->getResponse(), ['sent' => $sent]),
            $request,
        );
    }
}
