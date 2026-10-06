<?php

namespace Datalogix\Guardian\Tests\Feature\Framework\Livewire;

use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Framework\Livewire\Layout;
use Datalogix\Guardian\Framework\Livewire\Pages\Login;
use Datalogix\Guardian\Framework\Livewire\Pages\SignUp;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use ReflectionMethod;

#[Group('livewire')]
class PageLayoutTest extends TestCase
{
    protected function fortresses(): array
    {
        return [Fortress::make()->livewire()->product()->default()->layoutForPage('sign-up', Layout::Split)];
    }

    protected function layoutOf(string $page): string
    {
        return (new ReflectionMethod($page, 'getLayout'))->invoke(new $page);
    }

    public function test_a_page_without_a_layout_hint_uses_the_simple_layout(): void
    {
        $this->assertNull(Guardian::getLayoutForPage('login'));
        $this->assertSame(Layout::Simple->value, $this->layoutOf(Login::class));
    }

    public function test_a_page_follows_the_layout_hint_of_the_fortress(): void
    {
        $this->assertSame(Layout::Split->value, $this->layoutOf(SignUp::class));
    }

    public function test_the_layout_names_point_to_the_bundled_views(): void
    {
        $this->assertTrue(view()->exists(Layout::Simple->value));
        $this->assertTrue(view()->exists(Layout::Split->value));
    }
}
