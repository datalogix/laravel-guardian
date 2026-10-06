<?php

namespace Datalogix\Guardian\Framework\Inertia\Controllers;

use Datalogix\Guardian\Actions\CompleteOAuthRegistration as CompleteOAuthRegistrationAction;
use Datalogix\Guardian\Guardian;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class OAuthCompleteRegistrationController extends PageController
{
    protected static function page(): string
    {
        return 'oauth-complete-registration';
    }

    protected function props(Request $request): array
    {
        $pending = Guardian::getPendingOAuthRegistrationSession();

        return [
            'identifierKey' => Guardian::getIdentifierKey()->value,
            'provider' => Arr::get($pending, 'provider'),
            'email' => Arr::get($pending, 'email'),
        ];
    }

    public function submit(Request $request)
    {
        $data = $request->validate(CompleteOAuthRegistrationAction::rules());

        $result = app(CompleteOAuthRegistrationAction::class)($data);

        return $this->respond(
            Guardian::respondToAuthFlow($result, Guardian::getOAuthFeature()->getResponse()),
            $request,
        );
    }
}
