<?php

namespace Datalogix\Guardian\Tests\Feature\Http;

use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Http\Responses\LoginResponse;
use Datalogix\Guardian\Http\Responses\OAuthCompleteRegistrationResponse;
use Datalogix\Guardian\Http\Responses\SignUpResponse;
use Datalogix\Guardian\Http\Responses\TwoFactorChallengeResponse;
use Datalogix\Guardian\Http\Responses\TwoFactorSetupResponse;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use PHPUnit\Framework\Attributes\Group;

class RedirectsToTwoFactorSetupResponsesTest extends TestCase
{
    protected function fortresses(): array
    {
        $fortress = Fortress::make()->basic()->emailVerification(isRequired: false)->twoFactor(requireSetupOnLogin: true);

        // OAuth needs Laravel Socialite, which is optional.
        return [class_exists(Socialite::class) ? $fortress->oauth(providers: ['github']) : $fortress];
    }

    public function test_login_response_redirects_to_two_factor_setup_when_required(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $response = (new LoginResponse)->toResponse(Request::create('/'));

        $this->assertSame(Guardian::getTwoFactorSetupFeature()->getUrl(), $response->getTargetUrl());
    }

    public function test_sign_up_response_redirects_to_two_factor_setup_when_required(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $response = (new SignUpResponse)->toResponse(Request::create('/'));

        $this->assertSame(Guardian::getTwoFactorSetupFeature()->getUrl(), $response->getTargetUrl());
    }

    public function test_two_factor_challenge_response_redirects_to_the_challenge_page(): void
    {
        $response = (new TwoFactorChallengeResponse)->toResponse(Request::create('/'));

        $this->assertSame(Guardian::getTwoFactorChallengeFeature()->getUrl(), $response->getTargetUrl());
    }

    public function test_two_factor_setup_response_redirects_to_the_setup_page(): void
    {
        $response = (new TwoFactorSetupResponse)->toResponse(Request::create('/'));

        $this->assertSame(Guardian::getTwoFactorSetupFeature()->getUrl(), $response->getTargetUrl());
    }

    #[Group('socialite')]
    public function test_oauth_complete_registration_response_redirects_to_the_registration_page(): void
    {
        $response = (new OAuthCompleteRegistrationResponse)->toResponse(Request::create('/'));

        $this->assertSame(Guardian::getOAuthCompleteRegistrationFeature()->getUrl(), $response->getTargetUrl());
    }

    public function test_login_response_redirects_to_setup_for_an_unauthenticated_pending_setup(): void
    {
        $user = $this->createUser();
        Guardian::startPendingTwoFactorSetup($user, remember: true);

        $response = (new LoginResponse)->toResponse(Request::create('/'));

        $this->assertSame(Guardian::getTwoFactorSetupFeature()->getUrl(), $response->getTargetUrl());
    }

    public function test_login_response_does_not_redirect_to_setup_for_a_guest_without_a_pending_setup(): void
    {
        $response = (new LoginResponse)->toResponse(Request::create('/'));

        $this->assertNotSame(Guardian::getTwoFactorSetupFeature()->getUrl(), $response->getTargetUrl());
    }
}
