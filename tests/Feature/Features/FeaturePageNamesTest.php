<?php

namespace Datalogix\Guardian\Tests\Feature\Features;

use Datalogix\Guardian\Features\EmailVerificationVerifyFeature;
use Datalogix\Guardian\Features\LogoutFeature;
use Datalogix\Guardian\Features\OAuthCompleteRegistrationFeature;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Tests\TestCase;

class FeaturePageNamesTest extends TestCase
{
    public function test_email_verification_verify_feature_page_name(): void
    {
        $this->assertSame(
            'email-verification-verify',
            (new EmailVerificationVerifyFeature(Fortress::make()->basic()))->getPageName()
        );
    }

    public function test_logout_feature_page_name(): void
    {
        $this->assertSame(
            'logout',
            (new LogoutFeature(Fortress::make()->basic()))->getPageName()
        );
    }

    public function test_oauth_complete_registration_feature_page_name(): void
    {
        $this->assertSame(
            'oauth-complete-registration',
            (new OAuthCompleteRegistrationFeature(Fortress::make()->basic()))->getPageName()
        );
    }
}
