<?php

namespace Datalogix\Guardian\Tests\Feature\Support;

use Datalogix\Guardian\Support\Auth\FrameworkVerificationListener;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;

class FrameworkVerificationListenerTest extends TestCase
{
    public static function listeners(): array
    {
        return [
            'the class' => [SendEmailVerificationNotification::class, true],
            'the class and its method' => [SendEmailVerificationNotification::class.'@handle', true],
            'the class and its method as an array' => [[SendEmailVerificationNotification::class, 'handle'], true],
            'another class' => ['App\\Listeners\\WelcomeNewUser', false],
            'a closure' => [fn (Registered $event) => null, false],
        ];
    }

    #[DataProvider('listeners')]
    public function test_it_recognizes_the_listener_laravel_registers(mixed $listener, bool $recognized): void
    {
        Event::forget(Registered::class);
        Event::listen(Registered::class, $listener);

        $this->assertSame($recognized, FrameworkVerificationListener::isRegistered());
    }

    public function test_nothing_is_recognized_without_listeners(): void
    {
        Event::forget(Registered::class);

        $this->assertFalse(FrameworkVerificationListener::isRegistered());
    }
}
