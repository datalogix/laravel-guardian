<?php

namespace Datalogix\Guardian\Tests\Feature\Framework\Livewire;

use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Framework\Livewire\LivewireAdapter;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('livewire')]
class PerFortressViewsTest extends TestCase
{
    protected function fortresses(): array
    {
        return [
            Fortress::make()->livewire()->product()->default(),
            Fortress::make()->livewire(views: 'guardian-tests::admin-auth')->product('admin')->path('admin'),
        ];
    }

    protected function adapter(): LivewireAdapter
    {
        return app(LivewireAdapter::class);
    }

    public function test_a_page_uses_the_view_of_its_fortress_when_it_exists(): void
    {
        $this->get('/admin/login')->assertOk()->assertSee('ADMIN LOGIN VIEW');
        $this->get('/login')->assertOk()->assertDontSee('ADMIN LOGIN VIEW');
    }

    public function test_a_page_the_folder_does_not_have_falls_back_to_the_bundled_view(): void
    {
        $admin = Guardian::getFortress('admin');

        $this->assertSame('guardian-tests::admin-auth.login', $this->adapter()->viewFor('login', $admin));
        $this->assertSame('guardian::sign-up', $this->adapter()->viewFor('sign-up', $admin));

        $this->get('/admin/sign-up')->assertOk()->assertDontSee('ADMIN LOGIN VIEW')->assertSee('guardian test page: sign-up');
    }

    public function test_a_fortress_without_views_uses_the_bundled_ones(): void
    {
        $this->assertSame('guardian::login', $this->adapter()->viewFor('login', Guardian::getDefaultFortress()));
    }

    public function test_a_trailing_dot_in_the_folder_is_tolerated(): void
    {
        $fortress = Fortress::make()->livewire(views: 'guardian-tests::admin-auth.');

        $this->assertSame('guardian-tests::admin-auth.login', $this->adapter()->viewFor('login', $fortress));
    }
}
