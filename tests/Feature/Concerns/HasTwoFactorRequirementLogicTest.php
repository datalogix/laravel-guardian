<?php

namespace Datalogix\Guardian\Tests\Feature\Concerns;

use Datalogix\Guardian\Actions\EnableTwoFactor;
use Datalogix\Guardian\Actions\PrepareTwoFactorSetup;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Tests\Attributes\WithFortresses;
use Datalogix\Guardian\Tests\Fixtures\NonModelTwoFactorUser;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;
use PragmaRX\Google2FA\Google2FA;

class HasTwoFactorRequirementLogicTest extends TestCase
{
    protected function fortresses(): array
    {
        return [Fortress::make()->basic()->twoFactor()];
    }

    public function test_requires_challenge_is_false_for_a_null_user(): void
    {
        $this->assertFalse(Guardian::requiresTwoFactorChallenge(null));
    }

    public function test_requires_setup_is_false_for_a_null_user_when_required_on_login(): void
    {
        $fortress = Fortress::make()->basic()->twoFactor(requireSetupOnLogin: true);
        Guardian::setCurrentFortress($fortress);

        $this->assertFalse(Guardian::requiresTwoFactorSetup(null));
    }

    public function test_requires_setup_is_false_when_the_setup_feature_is_disabled(): void
    {
        $fortress = Fortress::make()->basic()->twoFactor(setupRouteAction: false, requireSetupOnLogin: true);
        Guardian::setCurrentFortress($fortress);

        $this->assertFalse(Guardian::requiresTwoFactorSetup($this->createUser()));
    }

    protected function enableTwoFactorFor($user): void
    {
        $this->actingAs($user);
        session()->put('auth.password_confirmed_at', time());
        $setup = app(PrepareTwoFactorSetup::class)($user);
        $code = app(Google2FA::class)->getCurrentOtp($setup['secret']);
        app(EnableTwoFactor::class)($user, ['code' => $code]);
        $this->app['auth']->guard()->logout();
        session()->flush();
    }

    public function test_a_custom_requirement_policy_overrides_the_default_decision(): void
    {
        $user = $this->createUser();
        $this->enableTwoFactorFor($user);

        // Even though two-factor is enabled for this user, the custom policy
        // unconditionally opts them out of the challenge.
        $fortress = Fortress::make()->basic()->twoFactor(requireWhen: fn ($user, $fortress, $isEnabled) => false);
        Guardian::setCurrentFortress($fortress);

        $this->assertFalse(Guardian::requiresTwoFactorChallenge($user->fresh()));
    }

    public function test_a_recently_confirmed_grace_period_skips_the_challenge(): void
    {
        $user = $this->createUser();
        $this->enableTwoFactorFor($user);

        $fortress = Fortress::make()->basic()->twoFactor(gracePeriodDays: 7);
        Guardian::setCurrentFortress($fortress);

        $this->assertFalse(Guardian::requiresTwoFactorChallenge($user->fresh()));
    }

    public function test_get_two_factor_setup_secret_reads_the_pending_setup_session(): void
    {
        $this->assertNull(Guardian::getTwoFactorSetupSecret());

        Guardian::startTwoFactorSetup('a-secret-value');

        $this->assertSame('a-secret-value', Guardian::getTwoFactorSetupSecret());
    }

    public function test_the_grace_period_does_not_apply_without_a_confirmed_at_timestamp(): void
    {
        // A non-Model TwoFactorAuthenticatable user: TwoFactorUser's
        // getTwoFactorConfirmedAt() only ever tracks that timestamp on Eloquent
        // models, so isWithinTwoFactorGracePeriod() must treat the missing
        // timestamp as "not within the grace period" rather than assuming it
        // applies.
        $user = new NonModelTwoFactorUser;

        $fortress = Fortress::make()->basic()->twoFactor(gracePeriodDays: 7);
        Guardian::setCurrentFortress($fortress);

        $this->assertTrue(Guardian::requiresTwoFactorChallenge($user));
    }

    protected function requiringSetupOnLogin(): array
    {
        return [Fortress::make()->basic()->twoFactor(requireSetupOnLogin: true)];
    }

    #[WithFortresses('requiringSetupOnLogin')]
    public function test_requires_setup_is_false_when_the_user_cannot_store_a_secret(): void
    {
        // A model with no two_factor_secret column and none of the storage
        // contracts implemented: canStoreTwoFactorSecret() must return false.
        $user = new class extends Model implements AuthenticatableContract
        {
            use Authenticatable;

            protected $table = 'oauth_identities';

            protected $guarded = [];
        };

        $this->assertFalse(Guardian::requiresTwoFactorSetup($user));
    }
}
