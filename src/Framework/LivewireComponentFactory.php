<?php

namespace Datalogix\Guardian\Framework;

use Datalogix\Guardian\Http\Livewire\ConfirmPassword;
use Datalogix\Guardian\Http\Livewire\EmailVerificationPrompt;
use Datalogix\Guardian\Http\Livewire\ForgotPassword;
use Datalogix\Guardian\Http\Livewire\Login;
use Datalogix\Guardian\Http\Livewire\OAuthCompleteRegistration;
use Datalogix\Guardian\Http\Livewire\ResetPassword;
use Datalogix\Guardian\Http\Livewire\SignUp;
use Datalogix\Guardian\Http\Livewire\TwoFactorChallenge;
use Datalogix\Guardian\Http\Livewire\TwoFactorSetup;
use InvalidArgumentException;

class LivewireComponentFactory implements ComponentFactory
{
    public function resolve(string $componentName): string
    {
        return match ($componentName) {
            'login' => Login::class,
            'sign-up' => SignUp::class,
            'forgot-password' => ForgotPassword::class,
            'reset-password' => ResetPassword::class,
            'confirm-password' => ConfirmPassword::class,
            'email-verification-prompt' => EmailVerificationPrompt::class,
            'two-factor-setup' => TwoFactorSetup::class,
            'two-factor-challenge' => TwoFactorChallenge::class,
            'oauth-complete-registration' => OAuthCompleteRegistration::class,
            default => throw new InvalidArgumentException("Unknown component [{$componentName}]."),
        };
    }
}
