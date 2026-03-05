<?php

namespace Datalogix\Guardian\Framework;

class LivewireComponentFactory implements ComponentFactory
{
    public function resolve(string $componentName): string
    {
        return match ($componentName) {
            'login' => \Datalogix\Guardian\Http\Livewire\Login::class,
            'sign-up' => \Datalogix\Guardian\Http\Livewire\SignUp::class,
            'forgot-password' => \Datalogix\Guardian\Http\Livewire\ForgotPassword::class,
            'reset-password' => \Datalogix\Guardian\Http\Livewire\ResetPassword::class,
            'confirm-password' => \Datalogix\Guardian\Http\Livewire\ConfirmPassword::class,
            'email-verification-prompt' => \Datalogix\Guardian\Http\Livewire\EmailVerificationPrompt::class,
            'two-factor-setup' => \Datalogix\Guardian\Http\Livewire\TwoFactorSetup::class,
            'two-factor-challenge' => \Datalogix\Guardian\Http\Livewire\TwoFactorChallenge::class,
            default => throw new \InvalidArgumentException("Unknown component [{$componentName}]."),
        };
    }
}
