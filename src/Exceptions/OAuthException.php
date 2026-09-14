<?php

namespace Datalogix\Guardian\Exceptions;

use Illuminate\Validation\ValidationException;

class OAuthException extends ValidationException
{
    use Concerns\HasRateLimitedMessage;

    public static function rateLimited(int $seconds): static
    {
        return static::rateLimitedMessage('login', $seconds);
    }

    public static function providerNotEnabled(): static
    {
        return static::withMessages([
            'oauth' => [__('The provider is not enabled.')],
        ]);
    }

    public static function unableToAuthenticate(): static
    {
        return static::withMessages([
            'oauth' => [__('Unable to authenticate with the provider.')],
        ]);
    }

    public static function noAccountFound(): static
    {
        return static::withMessages([
            'oauth' => [__('No account was found for this provider.')],
        ]);
    }

    public static function cannotAccess(): static
    {
        return static::withMessages([
            'oauth' => [__('auth.failed')],
        ]);
    }

    public static function emailAlreadyExists(): static
    {
        return static::withMessages([
            'oauth' => [__('An account already exists for this e-mail.')],
        ]);
    }

    public static function manualLinkRequired(): static
    {
        return static::withMessages([
            'oauth' => [__('Manual account linking is required for this e-mail.')],
        ]);
    }

    public static function unableToRedirect(): static
    {
        return static::withMessages([
            'oauth' => [__('Unable to redirect to the provider.')],
        ]);
    }

    public static function providerNotConfigured(): static
    {
        return static::withMessages([
            'oauth' => [__('This provider is not available right now.')],
        ]);
    }

    public static function identityAlreadyLinked(): static
    {
        return static::withMessages([
            'oauth' => [__('This account is already linked to another user.')],
        ]);
    }

    public static function registrationNotPending(): static
    {
        return static::withMessages([
            'oauth' => [__('There is no pending registration to complete. Please sign in again.')],
        ]);
    }
}
