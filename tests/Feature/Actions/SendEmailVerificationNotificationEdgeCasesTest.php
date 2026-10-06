<?php

namespace Datalogix\Guardian\Tests\Feature\Actions;

use Datalogix\Guardian\Actions\SendEmailVerificationNotification;
use Datalogix\Guardian\Exceptions\GuardianException;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Model;

class SendEmailVerificationNotificationEdgeCasesTest extends TestCase
{
    protected function fortresses(): array
    {
        return [Fortress::make()->product()->default()];
    }

    public function test_it_does_nothing_for_a_user_that_does_not_implement_must_verify_email(): void
    {
        $user = new class extends Model
        {
            protected $table = 'users';

            protected $guarded = [];
        };
        $user->forceFill(['name' => 'X', 'email' => 'plain@example.com', 'password' => 'x'])->save();

        $result = app(SendEmailVerificationNotification::class)($user);

        $this->assertFalse($result);
    }

    public function test_it_throws_for_a_must_verify_email_user_without_the_notifiable_trait(): void
    {
        $user = new class extends Model implements \Illuminate\Contracts\Auth\MustVerifyEmail
        {
            use MustVerifyEmail;

            protected $table = 'users';

            protected $guarded = [];
        };
        $user->forceFill(['name' => 'X', 'email' => 'noverify@example.com', 'password' => 'x'])->save();

        $this->expectException(GuardianException::class);
        $this->expectExceptionMessage('must use the Notifiable trait');

        app(SendEmailVerificationNotification::class)($user);
    }
}
