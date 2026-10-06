<?php

namespace Datalogix\Guardian\Framework\Inertia\Controllers;

use Datalogix\Guardian\Actions\ConfirmTwoFactorChallenge as ConfirmTwoFactorChallengeAction;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Response\Redirector;
use Illuminate\Http\Request;

class TwoFactorChallengeController extends PageController
{
    protected static function page(): string
    {
        return 'two-factor-challenge';
    }

    public static function endpoints(): array
    {
        return [
            'submit' => ['post', '', 'submit'],
            'resend' => ['post', '/resend', 'resend'],
        ];
    }

    public function __invoke(Request $request)
    {
        if (! Guardian::hasPendingTwoFactorChallenge()) {
            return Redirector::redirectToLogin();
        }

        return $this->render($request);
    }

    protected function props(Request $request): array
    {
        return [
            'method' => Guardian::getPendingTwoFactorChallengeMethod()->value,
            'canRememberDevice' => Guardian::shouldTwoFactorRememberOnDevice(),
            'rememberDeviceDays' => Guardian::getTwoFactorRememberForDays(),
        ];
    }

    public function submit(Request $request)
    {
        $action = app(ConfirmTwoFactorChallengeAction::class);
        $data = $request->validate($action::rules());

        $result = $action($data);

        return $this->respond(
            Guardian::respondToAuthFlow($result, Guardian::getLoginFeature()->getResponse()),
            $request,
        );
    }

    public function resend()
    {
        Guardian::resendPendingTwoFactorChallengeCode();

        return back();
    }
}
