<?php

namespace Datalogix\Guardian\Tests\Feature\TwoFactor;

use Datalogix\Guardian\Enums\TwoFactorMethod;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Support\TwoFactor\TwoFactorSessionManager;
use Datalogix\Guardian\Tests\TestCase;

class TwoFactorSessionManagerTest extends TestCase
{
    protected TwoFactorSessionManager $sessions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sessions = app(TwoFactorSessionManager::class);
    }

    protected function fortress()
    {
        return Guardian::getCurrentOrDefaultFortress();
    }

    public function test_challenge_lifecycle(): void
    {
        $user = $this->createUser();

        $this->sessions->startChallenge($this->fortress(), $user, true, TwoFactorMethod::Totp);

        $challenge = $this->sessions->getChallenge($this->fortress());
        $this->assertSame($user->id, $challenge['user_id']);
        $this->assertTrue($challenge['remember']);
        $this->assertSame('totp', $challenge['method']);

        $this->sessions->updateChallenge($this->fortress(), function (array $state) {
            $state['remember_device'] = true;

            return $state;
        });

        $this->assertTrue($this->sessions->getChallenge($this->fortress())['remember_device']);

        $this->sessions->clearChallenge($this->fortress());
        $this->assertNull($this->sessions->getChallenge($this->fortress()));
    }

    public function test_setup_lifecycle(): void
    {
        $this->sessions->startSetup($this->fortress(), 'secret-value', TwoFactorMethod::Email);

        $setup = $this->sessions->getSetup($this->fortress());
        $this->assertSame('secret-value', $setup['secret']);
        $this->assertSame('email', $setup['method']);

        $this->sessions->clearSetup($this->fortress());
        $this->assertNull($this->sessions->getSetup($this->fortress()));
    }

    public function test_pending_setup_lifecycle(): void
    {
        $user = $this->createUser();

        $this->sessions->startPendingSetup($this->fortress(), $user, false, TwoFactorMethod::Totp);

        $pending = $this->sessions->getPendingSetup($this->fortress());
        $this->assertSame($user->id, $pending['user_id']);
        $this->assertFalse($pending['remember']);

        $this->sessions->clearPendingSetup($this->fortress());
        $this->assertNull($this->sessions->getPendingSetup($this->fortress()));
    }
}
