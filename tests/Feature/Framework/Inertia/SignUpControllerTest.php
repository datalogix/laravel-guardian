<?php

namespace Datalogix\Guardian\Tests\Feature\Framework\Inertia;

use Datalogix\Guardian\Actions\SignUp as SignUpAction;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Support\Auth\PostAuthenticationFlow;
use Datalogix\Guardian\Tests\Attributes\WithFortresses;
use PHPUnit\Framework\Attributes\Group;

#[Group('inertia')]
class SignUpControllerTest extends InertiaTestCase
{
    protected function payload(array $overrides = []): array
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

    public function test_it_renders_the_sign_up_page(): void
    {
        $this->assertPage($this->inertiaGet('/sign-up'), 'Guardian/SignUp', [
            'identifierKey' => 'email',
            'loginUrl' => url('/login'),
            'oauthProviders' => [],
            'endpoints.submit' => url('/sign-up'),
        ]);
    }

    public function test_submit_creates_the_user_and_signs_them_in(): void
    {
        $this->inertiaPost('/sign-up', $this->payload())->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'new@example.com', 'name' => 'New User']);
        $this->assertAuthenticated();
    }

    public function test_submit_validates_the_payload(): void
    {
        $this->inertiaPost('/sign-up', $this->payload(['password_confirmation' => 'different']))
            ->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);
    }

    protected function withTerms(): array
    {
        return [Fortress::make()->inertia()->product()->default()->signUp(termsUrl: 'https://example.com/terms')];
    }

    #[WithFortresses('withTerms')]
    public function test_the_terms_are_linked_and_must_be_accepted(): void
    {
        $this->assertSame('https://example.com/terms', $this->inertiaGet('/sign-up')->json('props.termsUrl'));

        $this->inertiaPost('/sign-up', $this->payload(['terms' => false]))->assertSessionHasErrors('terms');
    }

    public function test_submit_rejects_an_email_that_is_already_registered(): void
    {
        $this->createUser(['email' => 'new@example.com']);

        $this->inertiaPost('/sign-up', $this->payload())->assertSessionHasErrors('login');
    }

    protected function bindASignUpAskingForAUsername(): void
    {
        $this->app->bind(SignUpAction::class, fn ($app) => new class($app->make(PostAuthenticationFlow::class)) extends SignUpAction
        {
            public static function rules(): array
            {
                return [...parent::rules(), 'username' => ['required', 'string']];
            }
        });
    }

    public function test_submit_keeps_the_fields_the_application_added_to_the_rules(): void
    {
        $this->bindASignUpAskingForAUsername();

        $this->inertiaPost('/sign-up', $this->payload(['username' => 'newbie']))->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'new@example.com', 'username' => 'newbie']);
    }

    public function test_submit_requires_the_fields_the_application_added_to_the_rules(): void
    {
        $this->bindASignUpAskingForAUsername();

        $this->inertiaPost('/sign-up', $this->payload())->assertSessionHasErrors('username');

        $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);
    }
}
