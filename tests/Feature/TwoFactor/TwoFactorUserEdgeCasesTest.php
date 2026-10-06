<?php

namespace Datalogix\Guardian\Tests\Feature\TwoFactor;

use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Support\TwoFactor\TwoFactorUser;
use Datalogix\Guardian\Tests\Fixtures\BadConnectionUser;
use Datalogix\Guardian\Tests\Fixtures\ContractTwoFactorUser;
use Datalogix\Guardian\Tests\Fixtures\NonModelTwoFactorUser;
use Datalogix\Guardian\Tests\Fixtures\SecretOnlyTwoFactorUser;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TwoFactorUserEdgeCasesTest extends TestCase
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

    public function test_get_two_factor_secret_accepts_a_non_object_user(): void
    {
        $this->assertNull($this->manager->getTwoFactorSecret('not-an-object', $this->fortress()));
    }

    public function test_has_two_factor_enabled_is_true_without_a_confirmed_at_column(): void
    {
        Schema::table('oauth_identities', function ($table) {
            $table->text('two_factor_secret')->nullable();
        });

        try {
            $user = new class extends Model
            {
                protected $table = 'oauth_identities';

                protected $guarded = [];
            };
            $user->forceFill([
                'two_factor_secret' => Crypt::encryptString('a-secret'),
                'fortress_id' => 'default',
                'auth_guard' => 'web',
                'authenticatable_type' => 'App\\Models\\User',
                'authenticatable_id' => '1',
                'provider' => 'test',
                'provider_user_id' => 'test-1',
            ])->save();

            $this->assertTrue($this->manager->hasTwoFactorEnabled($user, $this->fortress()));
            $this->assertNull($this->manager->getTwoFactorConfirmedAt($user));
        } finally {
            Schema::table('oauth_identities', function ($table) {
                $table->dropColumn('two_factor_secret');
            });
        }
    }

    public function test_save_two_factor_secret_returns_false_when_it_cannot_be_stored(): void
    {
        $user = new \stdClass;

        $this->assertFalse($this->manager->saveTwoFactorSecret($user, $this->fortress(), 'secret'));
    }

    public function test_save_two_factor_confirmed_at_returns_false_when_it_cannot_be_stored(): void
    {
        $user = new \stdClass;

        $this->assertFalse($this->manager->saveTwoFactorConfirmedAt($user, now()));
    }

    public function test_consume_recovery_code_returns_false_when_codes_cannot_be_stored(): void
    {
        $user = new class extends Model
        {
            protected $table = 'oauth_identities';

            protected $guarded = [];
        };

        $this->assertFalse($this->manager->consumeTwoFactorRecoveryCode($user, $this->fortress(), 'any-code'));
    }

    public function test_consume_recovery_code_returns_false_for_a_blank_candidate(): void
    {
        $user = $this->createUser();
        $this->manager->saveTwoFactorRecoveryCodes($user, $this->fortress(), ['alpha-code']);

        $this->assertFalse($this->manager->consumeTwoFactorRecoveryCode($user->fresh(), $this->fortress(), '   '));
    }

    public function test_consume_recovery_code_via_the_contract_based_non_locked_path(): void
    {
        $user = new ContractTwoFactorUser(['name' => 'X', 'email' => 'contract-consume@example.com', 'password' => 'x']);
        $user->save();

        $this->manager->saveTwoFactorRecoveryCodes($user, $this->fortress(), ['alpha-code', 'beta-code']);

        $this->assertTrue($this->manager->consumeTwoFactorRecoveryCode($user, $this->fortress(), 'alpha-code'));
        $this->assertSame(['beta-code'], $this->manager->getTwoFactorRecoveryCodes($user, $this->fortress()));

        $this->assertFalse($this->manager->consumeTwoFactorRecoveryCode($user, $this->fortress(), 'not-a-code'));
    }

    public function test_decode_stored_recovery_codes_ignores_invalid_json(): void
    {
        $user = $this->createUser();
        DB::table('users')->where('id', $user->id)->update(['two_factor_recovery_codes' => 'not-json']);

        $this->assertSame([], $this->manager->getTwoFactorRecoveryCodes($user->fresh(), $this->fortress()));
        $this->assertSame(0, $this->manager->getTwoFactorRecoveryCodesCount($user->fresh(), $this->fortress()));
    }

    public function test_recovery_codes_skip_non_string_and_blank_entries(): void
    {
        $user = $this->createUser();
        DB::table('users')->where('id', $user->id)->update([
            'two_factor_recovery_codes' => json_encode(['', null, 123, 'sha256:abcnotarealmatch', 'real-code']),
        ]);

        $this->assertTrue($this->manager->consumeTwoFactorRecoveryCode($user->fresh(), $this->fortress(), 'real-code'));
    }

    public function test_save_recovery_codes_returns_false_when_they_cannot_be_stored(): void
    {
        $user = new class extends Model
        {
            protected $table = 'oauth_identities';

            protected $guarded = [];
        };

        $this->assertFalse($this->manager->saveTwoFactorRecoveryCodes($user, $this->fortress(), ['a-code']));
    }

    public function test_secret_cache_key_falls_back_to_object_id_for_non_model_users(): void
    {
        $user = new NonModelTwoFactorUser;

        $this->assertTrue($this->manager->hasTwoFactorEnabled($user, $this->fortress()));
        $this->assertSame('a-secret-value', $this->manager->getTwoFactorSecret($user, $this->fortress()));

        // Calling it twice exercises the resolved-secret cache keyed by
        // spl_object_id() instead of a Model primary key.
        $this->assertSame('a-secret-value', $this->manager->getTwoFactorSecret($user, $this->fortress()));
    }

    public function test_raw_stored_recovery_codes_accepts_a_non_object_user(): void
    {
        $this->assertSame(0, $this->manager->getTwoFactorRecoveryCodesCount('not-an-object', $this->fortress()));
    }

    public function test_has_column_reports_false_and_recovers_when_schema_inspection_fails(): void
    {
        $user = new BadConnectionUser(['name' => 'X', 'email' => 'bad-conn@example.com', 'password' => 'x']);

        $this->assertFalse($this->manager->canStoreTwoFactorSecret($user));
    }

    public function test_recovery_codes_are_read_from_a_column_cast_to_an_array(): void
    {
        $user = $this->createUser();
        $this->manager->saveTwoFactorRecoveryCodes($user, $this->fortress(), ['alpha-code', 'beta-code']);

        // An application may cast the column itself, so the value arrives already decoded.
        $casted = $user->fresh()->mergeCasts(['two_factor_recovery_codes' => 'array']);

        $this->assertSame(2, $this->manager->getTwoFactorRecoveryCodesCount($casted, $this->fortress()));
    }

    public function test_consume_recovery_code_with_lock_returns_false_when_the_row_was_deleted(): void
    {
        // Simulates the row being deleted between the caller obtaining the
        // user instance and the locked re-fetch inside the transaction.
        $user = $this->createUser();
        $this->manager->saveTwoFactorRecoveryCodes($user, $this->fortress(), ['alpha-code']);

        DB::table('users')->where('id', $user->id)->delete();

        $this->assertFalse($this->manager->consumeTwoFactorRecoveryCode($user, $this->fortress(), 'alpha-code'));
    }

    public function test_a_user_that_only_manages_its_secret_cannot_store_recovery_codes(): void
    {
        $user = new SecretOnlyTwoFactorUser(['name' => 'X', 'email' => 'secret-only@example.com', 'password' => 'x']);
        $user->forceFill(['id' => 999999]);

        $manager = app(TwoFactorUser::class);
        $fortress = Guardian::getCurrentOrDefaultFortress();

        $this->assertTrue($manager->canStoreTwoFactorSecret($user));
        $this->assertFalse($manager->canStoreTwoFactorRecoveryCodes($user));
        $this->assertTrue($manager->hasTwoFactorEnabled($user, $fortress));
        $this->assertSame([], $manager->getTwoFactorRecoveryCodes($user, $fortress));
    }
}
