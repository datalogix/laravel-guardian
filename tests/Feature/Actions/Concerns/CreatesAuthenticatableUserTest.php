<?php

namespace Datalogix\Guardian\Tests\Feature\Actions\Concerns;

use Datalogix\Guardian\Actions\Concerns\CreatesAuthenticatableUser;
use Datalogix\Guardian\Tests\Fixtures\GuardedUser;
use Datalogix\Guardian\Tests\Fixtures\User;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Database\QueryException;
use RuntimeException;

class CreatesAuthenticatableUserTest extends TestCase
{
    protected function harness(): object
    {
        return new class
        {
            use CreatesAuthenticatableUser;

            public function create(string $modelClass, array $attributes, \Closure $cannotAccess, \Closure $queryHandler, \Closure $massAssignmentHandler)
            {
                return $this->createAuthenticatableUser($modelClass, $attributes, $cannotAccess, $queryHandler, $massAssignmentHandler);
            }
        };
    }

    public function test_a_unique_constraint_violation_is_translated_through_the_query_exception_handler(): void
    {
        $this->createUser(['email' => 'taken@example.com']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('duplicate');

        $this->harness()->create(
            User::class,
            ['name' => 'Someone', 'email' => 'taken@example.com', 'password' => 'x'],
            fn () => new RuntimeException('cannot access'),
            fn () => new RuntimeException('duplicate'),
            fn () => new RuntimeException('mass assignment'),
        );
    }

    public function test_a_non_constraint_query_exception_is_rethrown_as_is(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('this_column_does_not_exist');

        $this->harness()->create(
            User::class,
            // "no such column" fails with a different SQLSTATE than a unique
            // constraint violation (23000), so it must be rethrown untouched.
            ['name' => 'Someone', 'email' => 'nocolumn@example.com', 'password' => 'x', 'this_column_does_not_exist' => 'x'],
            fn () => new RuntimeException('cannot access'),
            fn () => new RuntimeException('duplicate'),
            fn () => new RuntimeException('mass assignment'),
        );
    }

    public function test_mass_assignment_protection_is_translated_through_its_handler(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('mass assignment');

        $this->harness()->create(
            GuardedUser::class,
            ['name' => 'Someone'],
            fn () => new RuntimeException('cannot access'),
            fn () => new RuntimeException('duplicate'),
            fn () => new RuntimeException('mass assignment'),
        );
    }
}
