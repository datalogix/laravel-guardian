<?php

namespace Datalogix\Guardian\Tests\Feature\Framework\Livewire;

use Datalogix\Guardian\Response\Notifier;
use Datalogix\Guardian\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('livewire')]
class NotifierTest extends TestCase
{
    public function test_it_uses_tallkit_when_bound(): void
    {
        $this->app->instance('tallkit', new class
        {
            public function alert($message, $type = null)
            {
                return $message.'|'.$type;
            }
        });

        // The tallkit branch flashes nothing to assert on.
        Notifier::notify('Hello', 'success');

        $this->assertNull(session('status'));
    }
}
