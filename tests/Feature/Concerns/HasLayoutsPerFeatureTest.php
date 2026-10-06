<?php

namespace Datalogix\Guardian\Tests\Feature\Concerns;

use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Framework\Livewire\Layout;
use Datalogix\Guardian\Tests\TestCase;

class HasLayoutsPerFeatureTest extends TestCase
{
    public function test_login_layout_is_scoped_to_the_login_page(): void
    {
        $fortress = Fortress::make()->basic()->login(layout: Layout::Split);

        $this->assertSame(Layout::Split->value, $fortress->getLayoutForPage('login'));
        $this->assertNull($fortress->getLayoutForPage('sign-up'));
    }

    public function test_password_confirmation_layout_is_scoped_to_its_page(): void
    {
        $fortress = Fortress::make()->basic()->passwordConfirmation(layout: Layout::Split);

        $this->assertSame(Layout::Split->value, $fortress->getLayoutForPage('confirm-password'));
    }

    public function test_forgot_and_reset_password_layouts_are_scoped_independently(): void
    {
        $fortress = Fortress::make()->basic()->passwordReset(
            forgotPasswordLayout: Layout::Split,
            resetPasswordLayout: Layout::Simple,
        );

        $this->assertSame(Layout::Split->value, $fortress->getLayoutForPage('forgot-password'));
        $this->assertSame(Layout::Simple->value, $fortress->getLayoutForPage('reset-password'));
    }

    public function test_email_verification_prompt_layout_is_scoped_to_its_page(): void
    {
        $fortress = Fortress::make()->basic()->emailVerification(promptLayout: Layout::Split);

        $this->assertSame(Layout::Split->value, $fortress->getLayoutForPage('email-verification-prompt'));
    }

    public function test_sign_up_layout_is_scoped_to_its_page(): void
    {
        $fortress = Fortress::make()->product()->default()->signUp(layout: Layout::Split);

        $this->assertSame(Layout::Split->value, $fortress->getLayoutForPage('sign-up'));
    }

    public function test_two_factor_challenge_and_setup_layouts_are_scoped_independently(): void
    {
        $fortress = Fortress::make()->basic()->twoFactor(
            challengeLayout: Layout::Split,
            setupLayout: Layout::Simple,
        );

        $this->assertSame(Layout::Split->value, $fortress->getLayoutForPage('two-factor-challenge'));
        $this->assertSame(Layout::Simple->value, $fortress->getLayoutForPage('two-factor-setup'));
    }

    public function test_oauth_layout_is_scoped_to_its_page(): void
    {
        $fortress = Fortress::make()->basic()->oauth(providers: ['github'], layout: Layout::Split);

        $this->assertSame(Layout::Split->value, $fortress->getLayoutForPage('oauth'));
    }

    public function test_forgot_password_url_returns_the_forgot_password_route_url(): void
    {
        $fortress = Fortress::make()->basic();

        $this->assertStringContainsString('/forgot-password', $fortress->forgotPasswordUrl());
    }
}
