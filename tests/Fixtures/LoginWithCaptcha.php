<?php

namespace Datalogix\Guardian\Tests\Fixtures;

use Datalogix\Guardian\Actions\Login;

/**
 * A login action an application binds, which asks for one more field.
 */
class LoginWithCaptcha extends Login
{
    public static function rules(): array
    {
        return [...parent::rules(), 'captcha' => ['required', 'in:human']];
    }
}
