<?php

namespace Datalogix\Guardian\Framework\Inertia\Controllers;

use Datalogix\Guardian\Actions\ForgotPassword as ForgotPasswordAction;
use Datalogix\Guardian\Guardian;
use Illuminate\Http\Request;

class ForgotPasswordController extends PageController
{
    protected static function page(): string
    {
        return 'forgot-password';
    }

    protected function props(Request $request): array
    {
        return [
            'identifierKey' => Guardian::getIdentifierKey()->value,
            'loginUrl' => Guardian::loginUrl(),
        ];
    }

    public function submit(Request $request)
    {
        $data = $request->validate(ForgotPasswordAction::rules());

        $status = app(ForgotPasswordAction::class)($data);

        return $this->respond(
            app(Guardian::getForgotPasswordFeature()->getResponse(), ['status' => $status]),
            $request,
        );
    }
}
