<?php

namespace Datalogix\Guardian\Tests\Feature\Support;

use Datalogix\Guardian\Support\SessionState;
use Datalogix\Guardian\Tests\TestCase;

class SessionStateTest extends TestCase
{
    protected SessionState $state;

    protected function setUp(): void
    {
        parent::setUp();

        $this->state = new SessionState;
    }

    public function test_put_and_get(): void
    {
        $this->state->put('guardian.key', ['foo' => 'bar']);

        $this->assertSame(['foo' => 'bar'], $this->state->get('guardian.key'));
    }

    public function test_get_returns_null_when_missing(): void
    {
        $this->assertNull($this->state->get('guardian.missing'));
    }

    public function test_get_valid_returns_state_when_ttl_disabled(): void
    {
        $this->state->put('guardian.key', ['started_at' => now()->subDay()->timestamp]);

        $this->assertNotNull($this->state->getValid('guardian.key', false));
        $this->assertNotNull($this->state->getValid('guardian.key', null));
    }

    public function test_get_valid_returns_null_when_expired(): void
    {
        $this->state->put('guardian.key', ['started_at' => now()->subMinutes(10)->timestamp]);

        $this->assertNull($this->state->getValid('guardian.key', 60));
        $this->assertNull($this->state->get('guardian.key'), 'Expired state should also be forgotten.');
    }

    public function test_get_valid_returns_state_when_within_ttl(): void
    {
        $this->state->put('guardian.key', ['started_at' => now()->timestamp]);

        $this->assertNotNull($this->state->getValid('guardian.key', 60));
    }

    public function test_get_valid_treats_missing_started_at_as_expired(): void
    {
        $this->state->put('guardian.key', ['foo' => 'bar']);

        $this->assertNull($this->state->getValid('guardian.key', 60));
    }

    public function test_update_mutates_existing_state(): void
    {
        $this->state->put('guardian.key', ['started_at' => now()->timestamp, 'count' => 1]);

        $result = $this->state->update('guardian.key', function (array $state) {
            $state['count']++;

            return $state;
        });

        $this->assertSame(2, $result['count']);
        $this->assertSame(2, $this->state->get('guardian.key')['count']);
    }

    public function test_update_returns_null_when_state_missing(): void
    {
        $result = $this->state->update('guardian.missing', fn (array $state) => $state);

        $this->assertNull($result);
    }

    public function test_update_returns_null_when_mutator_does_not_return_array(): void
    {
        $this->state->put('guardian.key', ['started_at' => now()->timestamp]);

        $result = $this->state->update('guardian.key', fn (array $state) => null);

        $this->assertNull($result);
    }

    public function test_forget(): void
    {
        $this->state->put('guardian.key', ['foo' => 'bar']);
        $this->state->forget('guardian.key');

        $this->assertNull($this->state->get('guardian.key'));
    }
}
