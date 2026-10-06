<?php

namespace Datalogix\Guardian\Tests\Feature\Actions\Concerns;

use Datalogix\Guardian\Actions\Concerns\HasRecentPasswordConfirmation;
use Datalogix\Guardian\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class HasRecentPasswordConfirmationTest extends TestCase
{
    protected function harness(): object
    {
        return new class
        {
            use HasRecentPasswordConfirmation;

            public function recentlyConfirmed(): bool
            {
                return $this->passwordWasRecentlyConfirmed();
            }
        };
    }

    public static function confirmations(): array
    {
        return [
            'never confirmed' => [null, false],
            'a value that is not a timestamp' => ['yesterday', false],
            'confirmed a moment ago' => [fn () => time() - 60, true],
            'confirmed as a numeric string' => [fn () => (string) (time() - 60), true],
            'confirmed longer ago than the timeout' => [fn () => time() - 10801, false],
        ];
    }

    #[DataProvider('confirmations')]
    public function test_the_password_counts_as_recently_confirmed_only_within_the_timeout(mixed $confirmedAt, bool $recent): void
    {
        config(['auth.password_timeout' => 10800]);

        if ($confirmedAt !== null) {
            session()->put('auth.password_confirmed_at', is_callable($confirmedAt) ? $confirmedAt() : $confirmedAt);
        }

        $this->assertSame($recent, $this->harness()->recentlyConfirmed());
    }
}
