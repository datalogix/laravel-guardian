<?php

namespace Datalogix\Guardian\Tests\Feature\Concerns;

use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Tests\TestCase;

class HasRoutesTest extends TestCase
{
    public function test_domains_defaults_to_empty(): void
    {
        $this->assertSame([], Fortress::make()->getDomains());
    }

    public function test_domain_sets_a_single_domain(): void
    {
        $fortress = Fortress::make()->domain('shop.test');

        $this->assertSame(['shop.test'], $fortress->getDomains());
    }

    public function test_domains_sets_multiple_domains(): void
    {
        $fortress = Fortress::make()->domains(['one.test', 'two.test']);

        $this->assertSame(['one.test', 'two.test'], $fortress->getDomains());
    }

    public function test_route_name_has_no_domain_prefix_with_zero_or_one_domain(): void
    {
        $fortress = Fortress::make()->id('default')->default()->domain('shop.test');

        $this->assertSame('auth.login', $fortress->generateRouteName('auth.login'));
    }

    public function test_route_name_is_prefixed_by_domain_with_multiple_domains(): void
    {
        $fortress = Fortress::make()->id('default')->default()->domains(['one.test', 'two.test']);

        $this->assertSame('one.test.auth.login', $fortress->generateRouteName('auth.login', domain: 'one.test'));
    }

    public function test_route_name_falls_back_to_the_current_domain_when_none_is_given(): void
    {
        $fortress = Fortress::make()->id('default')->default()->domains(['one.test', 'two.test']);

        Guardian::setCurrentDomain('two.test');

        $this->assertSame('two.test.auth.login', $fortress->generateRouteName('auth.login'));
    }

    public function test_home_url_defaults_to_the_fortress_path_url(): void
    {
        $fortress = Fortress::make()->path('dashboard');

        $this->assertSame(url('dashboard'), $fortress->getHomeUrl());
    }

    public function test_home_url_can_be_overridden_with_a_string_or_closure(): void
    {
        $this->assertSame('https://example.com/home', Fortress::make()->homeUrl('https://example.com/home')->getHomeUrl());
        $this->assertSame('https://closure.example.com', Fortress::make()->homeUrl(fn () => 'https://closure.example.com')->getHomeUrl());
    }
}
