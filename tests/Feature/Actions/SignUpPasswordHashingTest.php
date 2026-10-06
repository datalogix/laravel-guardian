<?php

namespace Datalogix\Guardian\Tests\Feature\Actions;

use Datalogix\Guardian\Actions\SignUp;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Tests\Fixtures\PlainPasswordUser;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SignUpPasswordHashingTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('auth.providers.users.model', PlainPasswordUser::class);
    }

    protected function fortresses(): array
    {
        return [Fortress::make()->product()->default()];
    }

    protected function signUp(): string
    {
        app(SignUp::class)([
            'name' => 'Plain',
            'login' => 'plain@example.com',
            'password' => 'PlainSecret123!',
            'password_confirmation' => 'PlainSecret123!',
            'terms' => true,
        ]);

        return DB::table('users')->where('email', 'plain@example.com')->value('password');
    }

    public function test_the_model_of_this_test_does_not_hash_the_password_by_itself(): void
    {
        $this->assertSame(PlainPasswordUser::class, Guardian::authModelClass());

        $plain = new PlainPasswordUser(['password' => 'typed']);

        $this->assertSame('typed', $plain->getAttributes()['password']);
    }

    public function test_a_model_without_the_hashed_cast_never_stores_the_password_in_plain_text(): void
    {
        $stored = $this->signUp();

        $this->assertNotSame('PlainSecret123!', $stored);
        $this->assertTrue(Hash::isHashed($stored));
        $this->assertTrue(Hash::check('PlainSecret123!', $stored));
    }
}
