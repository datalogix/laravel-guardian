<?php

namespace Datalogix\Guardian\Framework;

class InertiaComponentFactory implements ComponentFactory
{
    public function resolve(string $componentName): string
    {
        return match ($componentName) {
            'login' => \Datalogix\Guardian\Http\Inertia\Login::class,
            'sign-up' => \Datalogix\Guardian\Http\Inertia\SignUp::class,
            'forgot-password' => \Datalogix\Guardian\Http\Inertia\ForgotPassword::class,
            'reset-password' => \Datalogix\Guardian\Http\Inertia\ResetPassword::class,
            'confirm-password' => \Datalogix\Guardian\Http\Inertia\ConfirmPassword::class,
            'email-verification-prompt' => \Datalogix\Guardian\Http\Inertia\EmailVerificationPrompt::class,
            default => throw new \InvalidArgumentException("Unknown component [{$componentName}]."),
        };
    }
}
