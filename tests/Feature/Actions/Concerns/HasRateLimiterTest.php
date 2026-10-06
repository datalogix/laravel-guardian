<?php

namespace Datalogix\Guardian\Tests\Feature\Actions\Concerns;

use Datalogix\Guardian\Actions\Concerns\HasRateLimiter;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Support\Facades\RateLimiter;

class HasRateLimiterTest extends TestCase
{
    protected function harness(): object
    {
        return new class
        {
            use HasRateLimiter;

            public function reserve(string $key, $max, \Closure $onLockout, ?int $decaySeconds = null): mixed
            {
                return $this->reserveAttempt($key, $max, $onLockout, $decaySeconds);
            }

            public function action(\Closure $callback, $max, bool $clearOnSuccess = false): mixed
            {
                return $this->throttleAction($callback, fn () => 'locked out', 'key', $max, clearOnSuccess: $clearOnSuccess);
            }

            public function clear(string $key, $max): void
            {
                $this->clearRateLimiterIfThrottled($key, $max);
            }

            public function key(object $user): ?string
            {
                return $this->userKey($user);
            }

            public function throttle(?string $key = null, bool $includeIp = true): string
            {
                return $this->throttleKey($key, $includeIp);
            }
        };
    }

    public function test_nothing_is_counted_when_throttling_is_disabled(): void
    {
        $lockedOut = false;

        $this->harness()->reserve('key', false, function () use (&$lockedOut) {
            $lockedOut = true;
        });

        $this->assertFalse($lockedOut);
        $this->assertSame(0, RateLimiter::attempts('key'));
    }

    public function test_exactly_the_allowed_attempts_get_through(): void
    {
        $harness = $this->harness();
        $through = 0;

        for ($i = 0; $i < 5; $i++) {
            $harness->reserve('key', 3, fn () => 'locked out') ?? $through++;
        }

        $this->assertSame(3, $through);
    }

    public function test_attempts_sent_at_the_same_time_are_held_to_the_limit(): void
    {
        // Every attempt is under way before any of them has finished, as when they
        // are sent at the same time: none of them may slip past the limit.
        $harness = $this->harness();
        $underWay = 0;

        foreach (range(1, 50) as $attempt) {
            $harness->reserve('key', 5, fn () => 'locked out') ?? $underWay++;
        }

        $this->assertSame(5, $underWay);
    }

    public function test_actions_started_before_the_others_finish_are_held_to_the_limit(): void
    {
        $harness = $this->harness();
        $ran = 0;

        // Each action starts the next one before finishing, like concurrent requests.
        $action = function () use (&$action, &$ran, $harness) {
            $ran++;

            if ($ran < 20) {
                $harness->action($action, 3);
            }
        };

        $harness->action($action, 3);

        $this->assertSame(3, $ran);
    }

    public function test_a_successful_action_can_clear_its_attempts(): void
    {
        $harness = $this->harness();

        $harness->action(fn () => 'done', 3, clearOnSuccess: true);

        $this->assertSame(0, RateLimiter::attempts($harness->throttle('key')));
    }

    public function test_attempts_are_forgotten_after_their_decay(): void
    {
        $harness = $this->harness();

        $harness->reserve('key', 1, fn () => 'locked out', decaySeconds: 30);
        $this->assertSame('locked out', $harness->reserve('key', 1, fn () => 'locked out', decaySeconds: 30));

        $this->travel(31)->seconds();

        $this->assertNull($harness->reserve('key', 1, fn () => 'locked out', decaySeconds: 30));
    }

    public function test_clear_is_a_no_op_when_throttling_is_disabled(): void
    {
        RateLimiter::hit('key');

        $this->harness()->clear('key', false);

        $this->assertSame(1, RateLimiter::attempts('key'));
    }

    public function test_user_key_is_null_for_objects_without_an_auth_identifier(): void
    {
        $this->assertNull($this->harness()->key(new \stdClass));
    }

    public function test_user_key_combines_guard_fortress_and_identifier(): void
    {
        $user = $this->createUser();

        $key = $this->harness()->key($user);

        $this->assertSame('web|default|'.$user->id, $key);
    }

    protected function fromIp(string $ip): void
    {
        $this->app['request']->server->set('REMOTE_ADDR', $ip);
    }

    public function test_the_throttle_key_tells_clients_apart_by_ip(): void
    {
        $this->fromIp('10.0.0.1');
        $first = $this->harness()->throttle('someone@example.com');

        $this->fromIp('10.0.0.2');
        $second = $this->harness()->throttle('someone@example.com');

        $this->assertNotSame($first, $second);
    }

    public function test_the_throttle_key_can_ignore_the_ip(): void
    {
        $this->fromIp('10.0.0.1');
        $first = $this->harness()->throttle('someone@example.com', includeIp: false);

        $this->fromIp('10.0.0.2');
        $second = $this->harness()->throttle('someone@example.com', includeIp: false);

        $this->assertSame($first, $second);
    }

    public function test_the_throttle_key_tells_keys_apart(): void
    {
        $harness = $this->harness();

        $this->assertNotSame($harness->throttle('someone@example.com'), $harness->throttle('other@example.com'));
        $this->assertNotSame($harness->throttle(), $harness->throttle('someone@example.com'));
    }

    public function test_the_throttle_key_tells_fortresses_apart(): void
    {
        $harness = $this->harness();

        Guardian::setCurrentFortress(Fortress::make()->basic('customers'));
        $customers = $harness->throttle('someone@example.com');

        Guardian::setCurrentFortress(Fortress::make()->basic('admins')->guard('admin'));
        $admins = $harness->throttle('someone@example.com');

        $this->assertNotSame($customers, $admins);
    }

    public function test_a_fortress_scopes_its_own_throttle_keys_to_itself(): void
    {
        // Two-factor challenges are throttled by the fortress, whichever is current.
        $key = fn (Fortress $fortress) => (new \ReflectionMethod($fortress, 'throttleKey'))->invoke($fortress, 'someone');

        Guardian::setCurrentFortress(Fortress::make()->basic('other'));

        $this->assertSame($key(Fortress::make()->basic('one')), $key(Fortress::make()->basic('one')));
        $this->assertNotSame($key(Fortress::make()->basic('one')), $key(Fortress::make()->basic('two')));
    }
}
