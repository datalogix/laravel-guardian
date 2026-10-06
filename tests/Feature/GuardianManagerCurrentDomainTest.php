<?php

namespace Datalogix\Guardian\Tests\Feature;

use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Tests\TestCase;
use ReflectionProperty;

class GuardianManagerCurrentDomainTest extends TestCase
{
    protected function overrideEnvironmentDetection(bool $runningInConsole): void
    {
        // Force runningUnitTests() to false so getCurrentDomain() falls through
        // past its "testing" branch, and pin runningInConsole()'s memoized
        // value directly (it's cached the first time it's called, which
        // already happened during this test's own application boot).
        $this->app->instance('env', 'production');

        $property = new ReflectionProperty($this->app, 'isRunningInConsole');
        $property->setAccessible(true);
        $property->setValue($this->app, $runningInConsole);
    }

    public function test_it_returns_localhost_when_running_in_console_outside_of_tests(): void
    {
        $this->overrideEnvironmentDetection(runningInConsole: true);

        $this->assertSame('localhost', Guardian::getCurrentDomain());
    }

    public function test_it_falls_back_to_the_request_host_outside_of_console_and_tests(): void
    {
        $this->overrideEnvironmentDetection(runningInConsole: false);

        $this->assertSame(request()->getHost(), Guardian::getCurrentDomain());
    }
}
