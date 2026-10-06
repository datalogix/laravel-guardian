<?php

namespace Datalogix\Guardian\Tests\Feature;

use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Tests\Attributes\WithFortresses;
use Datalogix\Guardian\Tests\TestCase;

class GuardianManagerCurrentDomainTest extends TestCase
{
    protected function runningInConsole(bool $runningInConsole): void
    {
        // runningInConsole() is memoized when the application boots.
        (fn () => $this->isRunningInConsole = $runningInConsole)->call($this->app);
    }

    public function test_without_a_request_it_is_the_given_default(): void
    {
        $this->runningInConsole(true);

        $this->assertSame('app.example.com', Guardian::getCurrentDomain('app.example.com'));
        $this->assertNull(Guardian::getCurrentDomain());
    }

    public function test_with_a_request_it_is_the_host_of_the_request(): void
    {
        $this->runningInConsole(false);

        try {
            $this->assertSame(request()->getHost(), Guardian::getCurrentDomain('app.example.com'));
        } finally {
            $this->runningInConsole(true);
        }
    }

    public function test_a_domain_set_for_the_request_comes_first(): void
    {
        Guardian::setCurrentDomain('www.example.com');

        $this->assertSame('www.example.com', Guardian::getCurrentDomain('app.example.com'));
    }

    protected function multiDomain(): array
    {
        return [Fortress::make()->basic()->domains(['app.example.com', 'www.example.com'])];
    }

    #[WithFortresses('multiDomain')]
    public function test_a_queue_worker_builds_the_links_of_the_first_domain(): void
    {
        // A worker renders the e-mails, with no request to take the domain from.
        $this->runningInConsole(true);

        $this->assertStringStartsWith('http://app.example.com/', Guardian::getResetPasswordUrl('token', $this->createUser()));
    }
}
