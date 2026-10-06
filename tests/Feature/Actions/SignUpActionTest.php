<?php

namespace Datalogix\Guardian\Tests\Feature\Actions;

use Datalogix\Guardian\Actions\SignUp;
use Datalogix\Guardian\Enums\AuthFlowResult;
use Datalogix\Guardian\Enums\IdentifierKey;
use Datalogix\Guardian\Exceptions\SignUpException;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Support\Auth\PostAuthenticationFlow;
use Datalogix\Guardian\Tests\Attributes\WithFortresses;
use Datalogix\Guardian\Tests\Fixtures\DenyingUser;
use Datalogix\Guardian\Tests\Fixtures\User;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;

class SignUpActionTest extends TestCase
{
    protected function fortresses(): array
    {
        return [Fortress::make()->product()->default()];
    }

    protected function withUsersWhoCannotSignIn(): array
    {
        config([
            'auth.providers.denied_users' => ['driver' => 'eloquent', 'model' => DenyingUser::class],
            'auth.guards.denied' => ['driver' => 'session', 'provider' => 'denied_users'],
        ]);

        return [Fortress::make()->product()->default()->guard('denied')];
    }

    protected function validData(array $overrides = []): array
    {
        return [
            'name' => 'New User',
            'login' => 'new@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'terms' => true,
            ...$overrides,
        ];
    }

    public function test_the_password_is_hashed_exactly_once(): void
    {
        app(SignUp::class)($this->validData());

        $stored = User::whereEmail('new@example.com')->firstOrFail()->password;

        $this->assertTrue(Hash::isHashed($stored));
        $this->assertTrue(Hash::check('Password123!', $stored));
    }

    public function test_it_creates_a_user_and_authenticates(): void
    {
        $result = app(SignUp::class)($this->validData());

        $this->assertSame(AuthFlowResult::Authenticated, $result);
        $this->assertDatabaseHas('users', ['email' => 'new@example.com']);
        $this->assertTrue(Guardian::isAuthenticated());
    }

    public function test_it_fires_the_registered_event(): void
    {
        Event::fake([Registered::class]);

        app(SignUp::class)($this->validData());

        Event::assertDispatched(Registered::class);
    }

    public function test_the_verification_email_is_sent_once_by_the_listener_of_laravel(): void
    {
        Notification::fake();

        app(SignUp::class)($this->validData());

        Notification::assertSentToTimes(User::whereEmail('new@example.com')->firstOrFail(), VerifyEmail::class, 1);
    }

    public function test_guardian_sends_the_verification_email_when_the_application_does_not_listen_for_it(): void
    {
        // An application that removed the listener Laravel registers out of the box.
        Event::forget(Registered::class);
        Notification::fake();

        app(SignUp::class)($this->validData());

        Notification::assertSentToTimes(User::whereEmail('new@example.com')->firstOrFail(), VerifyEmail::class, 1);
    }

    #[WithFortresses('withUsersWhoCannotSignIn')]
    public function test_it_rejects_users_who_cannot_access_the_fortress(): void
    {
        $this->expectException(SignUpException::class);
        $this->expectExceptionMessage(SignUpException::cannotAccess()->getMessage());

        app(SignUp::class)($this->validData());
    }

    public function test_fields_outside_the_form_do_not_reach_the_model(): void
    {
        // The fixture model has $guarded = [], so it would take any of them.
        app(SignUp::class)($this->validData([
            'email_verified_at' => now(),
            'can_access' => false,
        ]));

        $user = User::whereEmail('new@example.com')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        $this->assertTrue($user->can_access);
    }

    public function test_an_application_adds_fields_by_extending_the_rules(): void
    {
        $signUp = new class(app(PostAuthenticationFlow::class)) extends SignUp
        {
            public static function rules(): array
            {
                return [...parent::rules(), 'username' => ['required', 'string']];
            }
        };

        $signUp($this->validData(['username' => 'newbie']));

        $this->assertSame('newbie', User::whereEmail('new@example.com')->firstOrFail()->username);
    }

    public function test_it_is_rate_limited(): void
    {
        $maxAttempts = Guardian::getSignUpFeature()->getMaxAttempts();

        // Reuses the same email on every attempt: the first call creates the user,
        // every later call fails uniqueness, but all of them share the same throttle
        // key (derived from the submitted `login` value) so attempts accumulate.
        for ($i = 0; $i < $maxAttempts; $i++) {
            try {
                app(SignUp::class)($this->validData());
            } catch (\Throwable) {
                // ignore, we only care about the throttle counter here
            }
        }

        $this->expectException(SignUpException::class);

        try {
            app(SignUp::class)($this->validData());
        } catch (SignUpException $exception) {
            $this->assertStringContainsString('seconds', $exception->errors()['login'][0]);

            throw $exception;
        }
    }

    public function test_rules_require_terms_acceptance_and_unique_email(): void
    {
        $this->createUser(['email' => 'taken@example.com']);

        $rules = SignUp::rules();

        $validator = Validator::make(
            $this->validData(['login' => 'taken@example.com', 'terms' => false]),
            $rules
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('terms', $validator->errors()->toArray());
        $this->assertArrayHasKey('login', $validator->errors()->toArray());
    }

    protected function identifiedByUsername(): array
    {
        return [Fortress::make()->product()->default()->identifierKey(IdentifierKey::Username)];
    }

    #[WithFortresses('identifiedByUsername')]
    public function test_rules_require_a_separate_email_field_when_the_identifier_is_not_email(): void
    {
        $rules = SignUp::rules();

        $this->assertArrayHasKey('email', $rules);
        $this->assertArrayHasKey('login', $rules);
    }
}
