<?php

namespace Datalogix\Guardian\Framework\Inertia\Controllers;

use Datalogix\Guardian\Actions\ResetPassword as ResetPasswordAction;
use Datalogix\Guardian\Guardian;
use Illuminate\Http\Request;

class ResetPasswordController extends PageController
{
    protected static function page(): string
    {
        return 'reset-password';
    }

    protected function props(Request $request): array
    {
        return [
            'identifierKey' => Guardian::getIdentifierKey()->value,
            'token' => (string) ($request->route('token') ?? $request->string('token')),
            'login' => $request->string('login')->toString(),
        ];
    }

    public function submit(Request $request)
    {
        $action = app(ResetPasswordAction::class);
        $data = $request->validate($action::rules());

        $status = $action($data);

        return $this->respond(
            app(Guardian::getResetPasswordFeature()->getResponse(), ['status' => $status]),
            $request,
        );
    }
}
