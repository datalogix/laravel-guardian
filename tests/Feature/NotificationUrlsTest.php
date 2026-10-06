<?php

namespace Datalogix\Guardian\Tests\Feature;

use Closure;
use Datalogix\Guardian\Actions\ForgotPassword;
use Datalogix\Guardian\Actions\SendEmailVerificationNotification;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Tests\Attributes\WithFortresses;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Queued e-mails build their links in a queue worker.
 */
class NotificationUrlsTest extends TestCase
{
    protected function fortresses(): array
    {
        return [
            Fortress::make()->product()->default(),
            Fortress::make()->admin(),
        ];
    }

    protected function withoutEmailLinks(): array
    {
        return [Fortress::make()->id('default')->default()->login()];
    }

    protected function withLinksOfTheApplication(): array
    {
        ResetPassword::createUrlUsing(fn () => 'https://app.test/my-own-reset-page');
        VerifyEmail::createUrlUsing(fn () => 'https://app.test/my-own-verify-page');

        return $this->fortresses();
    }

    /**
     * Renders like a queue worker: in a new application, with the context the job carried.
     */
    protected function renderedByAQueueWorker(object $notification, object $notifiable, ?array $context): Request
    {
        $payload = serialize($notification);

        // Nothing static survives into a new process.
        ResetPassword::createUrlUsing(null);
        VerifyEmail::createUrlUsing(null);
        $this->refreshApplication();

        Context::hydrate($context);

        return Request::create(unserialize($payload)->toMail($notifiable)->actionUrl);
    }

    protected function queuedFrom(string $fortress, Closure $send, string $notificationClass): array
    {
        Notification::fake();
        Guardian::setCurrentFortress(Guardian::getFortress($fortress));

        $notifiable = $send();

        $sent = Notification::sent($notifiable, $notificationClass)->first();
        $this->assertNotNull($sent, "No [{$notificationClass}] was sent.");

        return [$sent, $notifiable, Context::dehydrate()];
    }

    public function test_a_reset_link_rendered_by_a_queue_worker_is_a_signed_link_to_the_reset_page(): void
    {
        [$notification, $user, $context] = $this->queuedFrom('product', function () {
            $user = $this->createUser();
            app(ForgotPassword::class)(['login' => $user->email]);

            return $user;
        }, ResetPassword::class);

        $request = $this->renderedByAQueueWorker($notification, $user, $context);

        $this->assertTrue(URL::hasValidSignature($request));
        $this->assertSame($user->email, $request->query('login'));
        $this->assertStringEndsWith('/'.$notification->token, $request->path());
    }

    public function test_a_verification_link_rendered_by_a_queue_worker_is_a_signed_link_to_the_verify_page(): void
    {
        [$notification, $user, $context] = $this->queuedFrom('product', function () {
            $user = $this->createUser(['email_verified_at' => null]);
            app(SendEmailVerificationNotification::class)($user);

            return $user;
        }, VerifyEmail::class);

        $request = $this->renderedByAQueueWorker($notification, $user, $context);

        $this->assertTrue(URL::hasValidSignature($request));
        $this->assertStringContainsString("/verify/{$user->getKey()}/".sha1($user->email), $request->path());
    }

    public function test_a_queued_email_links_to_the_fortress_that_sent_it(): void
    {
        $expected = Request::create(
            Guardian::getFortress('admin')->getResetPasswordUrl('the-token', $this->createUser(['email' => 'admin@example.com']))
        )->path();

        [$notification, $user, $context] = $this->queuedFrom('admin', function () {
            $user = $this->createUser();
            app(ForgotPassword::class)(['login' => $user->email]);

            return $user;
        }, ResetPassword::class);

        $request = $this->renderedByAQueueWorker($notification, $user, $context);

        $this->assertSame(str_replace('the-token', $notification->token, $expected), $request->path());
        $this->assertSame('admin', Guardian::getCurrentFortress()->getId());
    }

    #[WithFortresses('withLinksOfTheApplication')]
    public function test_links_the_application_builds_itself_are_kept(): void
    {
        $user = $this->createUser();

        $this->assertSame('https://app.test/my-own-reset-page', (new ResetPassword('token'))->toMail($user)->actionUrl);
        $this->assertSame('https://app.test/my-own-verify-page', (new VerifyEmail)->toMail($user)->actionUrl);
    }

    #[WithFortresses('withoutEmailLinks')]
    public function test_the_links_are_left_alone_when_no_fortress_sends_such_e_mails(): void
    {
        $this->assertNull(ResetPassword::$createUrlCallback);
        $this->assertNull(VerifyEmail::$createUrlCallback);
    }
}
