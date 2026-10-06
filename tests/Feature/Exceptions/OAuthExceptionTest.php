<?php

namespace Datalogix\Guardian\Tests\Feature\Exceptions;

use Datalogix\Guardian\Exceptions\OAuthException;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;

class OAuthExceptionTest extends TestCase
{
    public static function messageFactories(): array
    {
        return [
            'providerNotEnabled' => ['providerNotEnabled', 'The provider is not enabled.'],
            'unableToAuthenticate' => ['unableToAuthenticate', 'Unable to authenticate with the provider.'],
            'noAccountFound' => ['noAccountFound', 'No account was found for this provider.'],
            'emailAlreadyExists' => ['emailAlreadyExists', 'An account already exists for this e-mail.'],
            'manualLinkRequired' => ['manualLinkRequired', 'Manual account linking is required for this e-mail.'],
            'unableToRedirect' => ['unableToRedirect', 'Unable to redirect to the provider.'],
            'providerNotConfigured' => ['providerNotConfigured', 'This provider is not available right now.'],
            'identityAlreadyLinked' => ['identityAlreadyLinked', 'This account is already linked to another user.'],
            'registrationNotPending' => ['registrationNotPending', 'There is no pending registration to complete. Please sign in again.'],
        ];
    }

    #[DataProvider('messageFactories')]
    public function test_static_factories_carry_an_oauth_error(string $factory, string $message): void
    {
        $exception = OAuthException::{$factory}();

        $this->assertInstanceOf(ValidationException::class, $exception);
        $this->assertSame([$message], $exception->errors()['oauth']);
    }

    public function test_cannot_access(): void
    {
        $this->assertSame([__('auth.failed')], OAuthException::cannotAccess()->errors()['oauth']);
    }

    public function test_rate_limited_uses_login_key(): void
    {
        $exception = OAuthException::rateLimited(15);

        $this->assertSame(['Too many attempts. Please try again in 15 seconds.'], $exception->errors()['login']);
    }
}
