<?php

namespace Datalogix\Guardian\Tests\Feature\TwoFactor;

use Datalogix\Guardian\Enums\TwoFactorMethod;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Notifications\TwoFactorSmsCodeNotification;
use Datalogix\Guardian\Support\TwoFactor\TwoFactorDeliveryManager;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;

/**
 * The two-factor codes go through a real (database) queue, as in production.
 */
class TwoFactorCodeQueueingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('jobs', function ($table) {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        config(['queue.default' => 'database', 'mail.default' => 'array']);
    }

    protected function sendCode(string $code = '482913'): void
    {
        app(TwoFactorDeliveryManager::class)->dispatch(
            Fortress::make()->basic(),
            $this->createUser(),
            TwoFactorMethod::Email,
            $code,
            'challenge',
        );
    }

    /**
     * @return array<int, string> the subjects of the e-mails sent while the queue ran
     */
    protected function runTheQueue(): array
    {
        $subjects = [];
        Event::listen(NotificationSent::class, function (NotificationSent $event) use (&$subjects) {
            $subjects[] = $event->notification->toMail($event->notifiable)->subject;
        });

        $this->artisan('queue:work', ['connection' => 'database', '--once' => true, '--queue' => 'default,two-factor'])->assertExitCode(0);

        return $subjects;
    }

    public function test_the_code_is_not_readable_in_the_queue(): void
    {
        $this->sendCode('482913');

        $payload = DB::table('jobs')->value('payload');

        $this->assertNotNull($payload);
        $this->assertStringNotContainsString('482913', $payload);
        $this->assertStringContainsString('482913', Crypt::decryptString(json_decode($payload, true)['data']['command']));
    }

    public function test_the_code_is_sent_in_the_language_of_the_request_that_asked_for_it(): void
    {
        app()->setLocale('pt_BR');
        $this->sendCode();

        // The worker speaks the default language of the application.
        app()->setLocale('en');

        $this->assertSame(['Seu código de autenticação de dois fatores'], $this->runTheQueue());
    }

    public function test_the_codes_can_have_a_queue_of_their_own(): void
    {
        config(['guardian.two_factor_codes.queue' => 'two-factor']);

        $this->sendCode();

        $this->assertSame('two-factor', DB::table('jobs')->value('queue'));
        $this->assertCount(1, $this->runTheQueue());
    }

    public function test_the_codes_can_be_sent_right_away(): void
    {
        config(['guardian.two_factor_codes.connection' => 'sync']);
        $sent = 0;
        Event::listen(NotificationSent::class, function () use (&$sent) {
            $sent++;
        });

        $this->sendCode();

        $this->assertSame(1, $sent);
        $this->assertSame(0, DB::table('jobs')->count());
    }

    public function test_the_sms_code_is_queued_the_same_way(): void
    {
        config(['guardian.two_factor_codes.connection' => 'redis', 'guardian.two_factor_codes.queue' => 'two-factor']);
        app()->setLocale('pt_BR');

        $notification = new TwoFactorSmsCodeNotification('482913', 'challenge');

        $this->assertInstanceOf(ShouldBeEncrypted::class, $notification);
        $this->assertSame('pt_BR', $notification->locale);
        $this->assertSame('redis', $notification->connection);
        $this->assertSame('two-factor', $notification->queue);
    }
}
