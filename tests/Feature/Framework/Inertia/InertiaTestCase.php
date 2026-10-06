<?php

namespace Datalogix\Guardian\Tests\Feature\Framework\Inertia;

use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Group;

#[Group('inertia')]
abstract class InertiaTestCase extends TestCase
{
    protected function fortresses(): array
    {
        return [Fortress::make()->inertia()->product()->default()];
    }

    protected function inertiaGet(string $uri): TestResponse
    {
        return $this->get($uri, ['X-Inertia' => 'true']);
    }

    protected function inertiaPost(string $uri, array $data = []): TestResponse
    {
        return $this->post($uri, $data, ['X-Inertia' => 'true']);
    }

    protected function inertiaDelete(string $uri): TestResponse
    {
        return $this->delete($uri, [], ['X-Inertia' => 'true']);
    }

    /**
     * @param  array<string, mixed>  $props  prop path (dot notation) => expected value
     */
    protected function assertPage(TestResponse $response, string $component, array $props = []): void
    {
        $response->assertOk()
            ->assertHeader('X-Inertia', 'true')
            ->assertJsonPath('component', $component);

        foreach ($props as $path => $expected) {
            $response->assertJsonPath("props.{$path}", $expected);
        }
    }
}
