<?php

namespace Datalogix\Guardian\Framework\Inertia\Controllers;

use Datalogix\Guardian\Actions\Login as LoginAction;
use Datalogix\Guardian\Guardian;
use Illuminate\Http\Request;

class LoginController extends PageController
{
    protected static function page(): string
    {
        return 'login';
    }

    protected function props(Request $request): array
    {
        return [
            'identifierKey' => Guardian::getIdentifierKey()->value,
            'forgotPasswordUrl' => Guardian::forgotPasswordUrl(),
            'signUpUrl' => Guardian::signUpUrl(),
            'oauthProviders' => $this->oauthProviders(),
        ];
    }

    public function submit(Request $request)
    {
        $data = $request->validate(LoginAction::rules());

        $result = app(LoginAction::class)($data, $request->boolean('remember'));

        return $this->respond(
            Guardian::respondToAuthFlow($result, Guardian::getLoginFeature()->getResponse()),
            $request,
        );
    }
}
