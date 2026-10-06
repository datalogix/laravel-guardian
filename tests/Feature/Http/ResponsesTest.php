<?php

namespace Datalogix\Guardian\Tests\Feature\Http;

use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Http\Responses\EmailVerificationPromptResponse;
use Datalogix\Guardian\Http\Responses\EmailVerificationVerifyResponse;
use Datalogix\Guardian\Http\Responses\ForgotPasswordResponse;
use Datalogix\Guardian\Http\Responses\LoginResponse;
use Datalogix\Guardian\Http\Responses\LogoutResponse;
use Datalogix\Guardian\Http\Responses\PasswordConfirmationResponse;
use Datalogix\Guardian\Http\Responses\ResetPasswordResponse;
use Datalogix\Guardian\Response\Notifier;
use Datalogix\Guardian\Response\Redirector;
use Datalogix\Guardian\Tests\Attributes\WithFortresses;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class ResponsesTest extends TestCase
{
    public function test_redirector_redirects_to_the_given_path(): void
    {
        $response = Redirector::redirect('/somewhere');

        $this->assertSame('http://localhost/somewhere', $response->getTargetUrl());
    }

    protected function withHome(): array
    {
        return [Fortress::make()->basic()->homeUrl('/dashboard')];
    }

    #[WithFortresses('withHome')]
    public function test_signing_in_goes_to_the_home_of_the_fortress(): void
    {
        $this->actingAs($this->createUser());

        $response = (new LoginResponse)->toResponse(request());

        $this->assertSame('http://localhost/dashboard', $response->getTargetUrl());
    }

    #[WithFortresses('withHome')]
    public function test_the_page_asked_for_before_signing_in_still_comes_first(): void
    {
        $this->actingAs($this->createUser());
        session()->put('url.intended', 'http://localhost/reports');

        $response = (new LoginResponse)->toResponse(request());

        $this->assertSame('http://localhost/reports', $response->getTargetUrl());
    }

    public function test_redirector_defaults_to_the_fortress_url(): void
    {
        $response = Redirector::redirect();

        $this->assertSame(Guardian::getUrl(), $response->getTargetUrl());
    }

    public function test_redirector_redirect_to_login(): void
    {
        $response = Redirector::redirectToLogin();

        $this->assertSame(Guardian::getLoginFeature()->getUrl(), $response->getTargetUrl());
    }

    public function test_notifier_flashes_a_status_message_by_default(): void
    {
        Notifier::notify('Something happened');

        $this->assertSame('Something happened', session('status'));
    }

    public function test_logout_response_redirects_to_login(): void
    {
        $response = (new LogoutResponse)->toResponse(Request::create('/'));

        $this->assertSame(Guardian::getLoginFeature()->getUrl(), $response->getTargetUrl());
    }

    public function test_password_confirmation_response_redirects_intended(): void
    {
        $response = (new PasswordConfirmationResponse)->toResponse(Request::create('/'));

        $this->assertSame(Guardian::getUrl(), $response->getTargetUrl());
    }

    public function test_email_verification_verify_response_redirects_intended(): void
    {
        $response = (new EmailVerificationVerifyResponse)->toResponse(Request::create('/'));

        $this->assertSame(Guardian::getUrl(), $response->getTargetUrl());
    }

    public function test_email_verification_prompt_response_flashes_success_status(): void
    {
        (new EmailVerificationPromptResponse(true))->toResponse(Request::create('/'));

        $this->assertSame('Verification link sent!', session('status'));
    }

    public function test_email_verification_prompt_response_flashes_failure_status(): void
    {
        (new EmailVerificationPromptResponse(false))->toResponse(Request::create('/'));

        $this->assertSame('Failed to send verification link. Please try again later.', session('status'));
    }

    public function test_email_verification_prompt_response_tells_how_long_to_wait_when_throttled(): void
    {
        (new EmailVerificationPromptResponse(false, retryAfter: 30))->toResponse(Request::create('/'));

        $this->assertSame('Too many attempts. Please try again in 30 seconds.', session('status'));
    }

    public function test_forgot_password_response_flashes_status_and_redirects(): void
    {
        $response = (new ForgotPasswordResponse(Password::RESET_LINK_SENT))->toResponse(Request::create('/'));

        $this->assertSame(__(Password::RESET_LINK_SENT), session('status'));
        $this->assertSame(Guardian::getForgotPasswordFeature()->getUrl(), $response->getTargetUrl());
    }

    public function test_reset_password_response_flashes_status_and_redirects_to_login(): void
    {
        $response = (new ResetPasswordResponse(Password::PASSWORD_RESET))->toResponse(Request::create('/'));

        $this->assertSame(__(Password::PASSWORD_RESET), session('status'));
        $this->assertSame(Guardian::getLoginFeature()->getUrl(), $response->getTargetUrl());
    }
}
