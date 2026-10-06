<?php

namespace Datalogix\Guardian\Framework\Inertia\Controllers;

use Datalogix\Guardian\Actions\ConfirmPassword as ConfirmPasswordAction;
use Datalogix\Guardian\Guardian;
use Illuminate\Http\Request;

class ConfirmPasswordController extends PageController
{
    protected static function page(): string
    {
        return 'confirm-password';
    }

    public function submit(Request $request)
    {
        $action = app(ConfirmPasswordAction::class);
        $data = $request->validate($action::rules());

        $action($data);

        return $this->respond(
            app(Guardian::getPasswordConfirmationFeature()->getResponse()),
            $request,
        );
    }
}
