<?php

namespace Datalogix\Guardian\Tests\Feature\Framework\Livewire\Pages;

use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Framework\Livewire\Pages\EmailVerificationPrompt;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Group;

#[Group('livewire')]
class EmailVerificationPromptMountTest extends TestCase
{
    protected function fortresses(): array
    {
        return [Fortress::make()->product()->default()];
    }

    public function test_it_does_nothing_for_a_user_that_does_not_implement_must_verify_email(): void
    {
        $user = new class extends Model implements \Illuminate\Contracts\Auth\Authenticatable
        {
            use Authenticatable;

            protected $table = 'users';

            protected $guarded = [];
        };
        $user->forceFill(['name' => 'X', 'email' => 'notmustverify@example.com', 'password' => 'x'])->save();

        $this->actingAs($user);

        Livewire::test(EmailVerificationPrompt::class)->assertOk();
    }

    public function test_it_redirects_an_already_verified_user(): void
    {
        $user = $this->createUser(['email_verified_at' => now()]);
        $this->actingAs($user);

        Livewire::test(EmailVerificationPrompt::class)->assertRedirect();
    }
}
