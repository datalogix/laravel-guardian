<?php

namespace Datalogix\Guardian\Tests\Feature\Http;

use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Http\Responses\LoginResponse;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

class EmailVerificationFlowTest extends TestCase
{
    protected function fortresses(): array
    {
        return [Fortress::make()->product()->default()];
    }

    public function test_prompt_is_shown_to_unverified_users(): void
    {
        $user = $this->createUser(['email_verified_at' => null]);

        $response = $this->actingAs($user)->get('/email-verification/prompt');

        $response->assertOk();
    }

    public function test_valid_signed_link_verifies_the_email(): void
    {
        Event::fake([Verified::class]);

        $user = $this->createUser(['email_verified_at' => null]);
        $url = Guardian::getVerifyEmailUrl($user);

        $response = $this->actingAs($user)->get($url);

        $response->assertRedirect();
        $this->assertNotNull($user->fresh()->email_verified_at);
        Event::assertDispatched(Verified::class);
    }

    public function test_invalid_hash_is_rejected(): void
    {
        $user = $this->createUser(['email_verified_at' => null]);
        $url = Guardian::getVerifyEmailUrl($user, ['hash' => sha1('wrong-email')]);

        $response = $this->actingAs($user)->get($url);

        $response->assertForbidden();
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_unsigned_link_is_rejected(): void
    {
        $user = $this->createUser(['email_verified_at' => null]);

        $response = $this->actingAs($user)->get("/email-verification/verify/{$user->id}/".sha1($user->email));

        $response->assertForbidden();
    }

    public function test_it_logs_out_and_redirects_to_login_when_verification_is_required_without_a_prompt_page(): void
    {
        // A standalone, unregistered Fortress: email verification is required
        // but the prompt route was explicitly disabled, a combination
        // FortressRegistry::validate() would normally reject at boot time.
        // Setting it as "current" directly bypasses that boot-time check so the
        // response class's own runtime fallback can be exercised in isolation.
        $fortress = Fortress::make()->basic()->emailVerification(promptRouteAction: false, isRequired: true);
        Guardian::setCurrentFortress($fortress);

        $user = $this->createUser(['email_verified_at' => null]);
        $this->actingAs($user);

        $response = (new LoginResponse)->toResponse(Request::create('/'));

        $this->assertFalse(Guardian::isAuthenticated());
        $this->assertSame(Guardian::getLoginFeature()->getUrl(), $response->getTargetUrl());
    }
}
