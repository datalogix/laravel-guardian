<?php

namespace Datalogix\Guardian\Framework\Inertia\Controllers;

use Datalogix\Guardian\Actions\SignUp as SignUpAction;
use Datalogix\Guardian\Guardian;
use Illuminate\Http\Request;

class SignUpController extends PageController
{
    protected static function page(): string
    {
        return 'sign-up';
    }

    protected function props(Request $request): array
    {
        return [
            'identifierKey' => Guardian::getIdentifierKey()->value,
            'loginUrl' => Guardian::loginUrl(),
            'termsUrl' => Guardian::getSignUpTermsUrl(),
            'oauthProviders' => $this->oauthProviders(),
        ];
    }

    public function submit(Request $request)
    {
        $action = app(SignUpAction::class);
        $data = $request->validate($action::rules());

        $result = $action($data);

        return $this->respond(
            Guardian::respondToAuthFlow($result, Guardian::getSignUpFeature()->getResponse()),
            $request,
        );
    }
}
