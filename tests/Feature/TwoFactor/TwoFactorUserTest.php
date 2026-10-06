<?php

namespace Datalogix\Guardian\Tests\Feature\TwoFactor;

use Datalogix\Guardian\Enums\TwoFactorMethod;
use Datalogix\Guardian\Exceptions\TwoFactorSecretDecryptionException;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Support\TwoFactor\TwoFactorUser;
use Datalogix\Guardian\Tests\Fixtures\ContractTwoFactorUser;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Support\Facades\DB;

class TwoFactorUserTest extends TestCase
{
    protected TwoFactorUser $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = app(TwoFactorUser::class);
    }

    protected function fortress()
    {
        return Guardian::getCurrentOrDefaultFortress();
    }

    public function test_column_based_secret_round_trips_through_encryption(): void
    {
        $user = $this->createUser();

        $this->manager->saveTwoFactorSecret($user, $this->fortress(), 'topsecret');

        $this->assertNotSame('topsecret', $user->fresh()->two_factor_secret);
        $this->assertSame('topsecret', $this->manager->getTwoFactorSecret($user->fresh(), $this->fortress()));
    }

    public function test_secret_is_prefixed_with_the_method_when_not_totp(): void
    {
        $user = $this->createUser();

        $this->manager->saveTwoFactorSecret($user, $this->fortress(), TwoFactorMethod::Email->value.':abc123');

        $this->assertSame(TwoFactorMethod::Email, $this->manager->getTwoFactorMethod($user->fresh(), $this->fortress()));
        $this->assertSame('abc123', $this->manager->getTwoFactorSecret($user->fresh(), $this->fortress()));
    }

    public function test_has_two_factor_enabled_requires_a_confirmed_at_timestamp(): void
    {
        $user = $this->createUser();

        $this->assertFalse($this->manager->hasTwoFactorEnabled($user, $this->fortress()));

        $this->manager->saveTwoFactorSecret($user, $this->fortress(), 'secret');
        $user = $user->fresh();

        $this->assertTrue($this->manager->hasTwoFactorEnabled($user, $this->fortress()));
        $this->assertNotNull($this->manager->getTwoFactorConfirmedAt($user));
    }

    public function test_saving_a_null_secret_clears_the_confirmed_at_timestamp(): void
    {
        $user = $this->createUser();
        $this->manager->saveTwoFactorSecret($user, $this->fortress(), 'secret');

        $this->manager->saveTwoFactorSecret($user->fresh(), $this->fortress(), null);

        $this->assertFalse($this->manager->hasTwoFactorEnabled($user->fresh(), $this->fortress()));
        $this->assertNull($this->manager->getTwoFactorConfirmedAt($user->fresh()));
    }

    public function test_corrupted_secret_throws_a_decryption_exception(): void
    {
        $user = $this->createUser();

        DB::table('users')->where('id', $user->id)->update(['two_factor_secret' => 'not-encrypted-data']);

        $this->expectException(TwoFactorSecretDecryptionException::class);
        $this->expectExceptionMessage('Unable to decrypt the stored two-factor secret.');

        $this->manager->getTwoFactorSecret($user->fresh(), $this->fortress());
    }

    public function test_recovery_codes_are_stored_hashed_and_consumable_once(): void
    {
        $user = $this->createUser();

        $this->manager->saveTwoFactorRecoveryCodes($user, $this->fortress(), ['alpha-code', 'beta-code']);

        $stored = DB::table('users')->where('id', $user->id)->value('two_factor_recovery_codes');
        $this->assertStringNotContainsString('alpha-code', $stored);

        $this->assertSame(2, $this->manager->getTwoFactorRecoveryCodesCount($user->fresh(), $this->fortress()));
        $this->assertTrue($this->manager->consumeTwoFactorRecoveryCode($user->fresh(), $this->fortress(), 'alpha-code'));
        $this->assertSame(1, $this->manager->getTwoFactorRecoveryCodesCount($user->fresh(), $this->fortress()));
        $this->assertFalse($this->manager->consumeTwoFactorRecoveryCode($user->fresh(), $this->fortress(), 'alpha-code'));
    }

    public function test_can_store_secret_and_recovery_codes_via_columns(): void
    {
        $user = $this->createUser();

        $this->assertTrue($this->manager->canStoreTwoFactorSecret($user));
        $this->assertTrue($this->manager->canStoreTwoFactorRecoveryCodes($user));
    }

    public function test_contract_based_user_stores_secret_and_recovery_codes_without_touching_columns(): void
    {
        $user = new ContractTwoFactorUser(['name' => 'Contract', 'email' => 'contract@example.com', 'password' => 'x']);
        $user->save();

        $this->assertTrue($this->manager->canStoreTwoFactorSecret($user));
        $this->assertTrue($this->manager->canStoreTwoFactorRecoveryCodes($user));

        $this->manager->saveTwoFactorSecret($user, $this->fortress(), 'contract-secret');
        $this->manager->saveTwoFactorRecoveryCodes($user, $this->fortress(), ['one', 'two']);

        $this->assertSame('contract-secret', $this->manager->getTwoFactorSecret($user, $this->fortress()));
        $this->assertTrue($this->manager->hasTwoFactorEnabled($user, $this->fortress()));
        // The model is given the hashes of the codes, never the codes themselves.
        $this->assertCount(2, $user->getTwoFactorRecoveryCodes($this->fortress()));
        $this->assertNotContains('one', $user->getTwoFactorRecoveryCodes($this->fortress()));
        $this->assertTrue($this->manager->consumeTwoFactorRecoveryCode($user, $this->fortress(), 'one'));

        // The contract methods are used directly — the DB column stays empty.
        $this->assertNull(DB::table('users')->where('id', $user->id)->value('two_factor_secret'));
    }
}
