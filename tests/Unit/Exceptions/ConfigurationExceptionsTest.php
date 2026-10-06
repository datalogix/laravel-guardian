<?php

namespace Datalogix\Guardian\Tests\Unit\Exceptions;

use Datalogix\Guardian\Exceptions\EmailVerificationConfigurationException;
use Datalogix\Guardian\Exceptions\FortressIdException;
use Datalogix\Guardian\Exceptions\FrameworkConfigurationException;
use Datalogix\Guardian\Exceptions\GuardianException;
use Datalogix\Guardian\Exceptions\IdentifierColumnConfigurationException;
use Datalogix\Guardian\Exceptions\IdentifierValueMissingException;
use Datalogix\Guardian\Exceptions\MultipleDefaultFortressesException;
use Datalogix\Guardian\Exceptions\NoDefaultFortressSetException;
use Datalogix\Guardian\Exceptions\NoFortressRegisteredException;
use Datalogix\Guardian\Exceptions\OAuthConfigurationException;
use Datalogix\Guardian\Exceptions\OAuthProviderNotConfiguredException;
use Datalogix\Guardian\Exceptions\TwoFactorSecretDecryptionException;
use Datalogix\Guardian\Exceptions\UnsupportedAuthGuardException;
use PHPUnit\Framework\TestCase;

class ConfigurationExceptionsTest extends TestCase
{
    public function test_email_verification_configuration_exception_missing_prompt_route(): void
    {
        $exception = EmailVerificationConfigurationException::missingPromptRoute('default');

        $this->assertInstanceOf(GuardianException::class, $exception);
        $this->assertStringContainsString('[default]', $exception->getMessage());
        $this->assertStringContainsString('promptRouteAction', $exception->getMessage());
    }

    public function test_email_verification_configuration_exception_missing_verify_route(): void
    {
        $exception = EmailVerificationConfigurationException::missingVerifyRoute('default');

        $this->assertStringContainsString('verifyRouteAction', $exception->getMessage());
    }

    public function test_fortress_id_exception_variants(): void
    {
        $this->assertStringContainsString('[default]', FortressIdException::alreadySet('default', 'other')->getMessage());
        $this->assertStringContainsString('[other]', FortressIdException::alreadySet('default', 'other')->getMessage());
        $this->assertStringContainsString('without an `id()`', FortressIdException::missing()->getMessage());
        $this->assertStringContainsString('[default]', FortressIdException::duplicateInRegistry('default')->getMessage());
        $this->assertStringContainsString('20', FortressIdException::tooLong('a-very-long-id', 20)->getMessage());
        $this->assertStringContainsString('[web]', FortressIdException::guardTooLong('web', 20)->getMessage());
    }

    public function test_framework_configuration_exception(): void
    {
        $exception = FrameworkConfigurationException::dependencyMissing('inertia', 'inertiajs/inertia-laravel');

        $this->assertInstanceOf(GuardianException::class, $exception);
        $this->assertStringContainsString('[inertia]', $exception->getMessage());
        $this->assertStringContainsString('composer require inertiajs/inertia-laravel', $exception->getMessage());
    }

    public function test_identifier_column_configuration_exception(): void
    {
        $missingColumn = IdentifierColumnConfigurationException::missingColumn('default', 'App\\Models\\User', 'cpf');
        $this->assertStringContainsString('[cpf]', $missingColumn->getMessage());
        $this->assertStringContainsString('App\\Models\\User', $missingColumn->getMessage());

        $missingEmail = IdentifierColumnConfigurationException::missingEmailColumn('default', 'App\\Models\\User');
        $this->assertStringContainsString('[email]', $missingEmail->getMessage());
    }

    public function test_identifier_value_missing_exception(): void
    {
        $exception = IdentifierValueMissingException::forUser('cpf', 42);

        $this->assertStringContainsString('[cpf]', $exception->getMessage());
        $this->assertStringContainsString('[42]', $exception->getMessage());
    }

    public function test_multiple_default_fortresses_exception(): void
    {
        $this->assertStringContainsString('Only one fortress can be the default', MultipleDefaultFortressesException::make()->getMessage());
    }

    public function test_no_default_fortress_set_exception(): void
    {
        $this->assertStringContainsString('No default Fortress is set', NoDefaultFortressSetException::make()->getMessage());
    }

    public function test_no_fortress_registered_exception(): void
    {
        $this->assertStringContainsString('No fortresses have been registered', NoFortressRegisteredException::make()->getMessage());
    }

    public function test_oauth_configuration_exception_socialite_not_installed(): void
    {
        $exception = OAuthConfigurationException::socialiteNotInstalled('default');

        $this->assertInstanceOf(GuardianException::class, $exception);
        $this->assertStringContainsString('[default]', $exception->getMessage());
        $this->assertStringContainsString('composer require laravel/socialite', $exception->getMessage());
    }

    public function test_oauth_provider_not_configured_exception(): void
    {
        $exception = OAuthProviderNotConfiguredException::make('default', 'github');

        $this->assertStringContainsString('[default]', $exception->getMessage());
        $this->assertStringContainsString('[github]', $exception->getMessage());
    }

    public function test_two_factor_secret_decryption_exception(): void
    {
        $exception = new TwoFactorSecretDecryptionException('boom');

        $this->assertInstanceOf(GuardianException::class, $exception);
        $this->assertSame('boom', $exception->getMessage());
    }

    public function test_unsupported_auth_guard_exception(): void
    {
        $forGuard = UnsupportedAuthGuardException::forGuard('web', 'FooGuard');
        $this->assertStringContainsString('[web]', $forGuard->getMessage());
        $this->assertStringContainsString('[FooGuard]', $forGuard->getMessage());

        $forProvider = UnsupportedAuthGuardException::forProvider('web', 'FooProvider');
        $this->assertStringContainsString('[FooProvider]', $forProvider->getMessage());
    }
}
