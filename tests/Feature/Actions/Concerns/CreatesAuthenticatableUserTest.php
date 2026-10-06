<?php

namespace Datalogix\Guardian\Tests\Feature\Actions\Concerns;

use Datalogix\Guardian\Actions\Concerns\CreatesAuthenticatableUser;
use Datalogix\Guardian\Tests\Fixtures\DefaultFillableUser;
use Datalogix\Guardian\Tests\Fixtures\GuardedUser;
use Datalogix\Guardian\Tests\Fixtures\User;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use PDOException;
use RuntimeException;

class CreatesAuthenticatableUserTest extends TestCase
{
    protected function harness(): object
    {
        return new class
        {
            use CreatesAuthenticatableUser;

            public function create(string $modelClass, array $attributes, \Closure $cannotAccess, \Closure $queryHandler, \Closure $massAssignmentHandler, array $guardianAttributes = [])
            {
                return $this->createAuthenticatableUser($modelClass, $attributes, $cannotAccess, $queryHandler, $massAssignmentHandler, $guardianAttributes);
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
            // Not a unique violation, so it is rethrown untouched.
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

    public function test_a_unique_constraint_violation_of_postgres_is_translated_too(): void
    {
        // Postgres reports it as 23505, where MySQL and SQLite report 23000.
        User::creating(fn () => throw new UniqueConstraintViolationException(
            'pgsql', 'insert into "users" ...', [], new PDOException('duplicate key value violates unique constraint', 23505),
        ));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('duplicate');

        $this->harness()->create(
            User::class,
            ['name' => 'Someone', 'email' => 'race@example.com', 'password' => 'x'],
            fn () => new RuntimeException('cannot access'),
            fn () => new RuntimeException('duplicate'),
            fn () => new RuntimeException('mass assignment'),
        );
    }

    public function test_another_integrity_violation_is_not_taken_for_a_duplicate(): void
    {
        // Each database words this error differently: only the type is checked.
        try {
            $this->harness()->create(
                User::class,
                ['email' => 'noname@example.com', 'password' => 'x'],
                fn () => new RuntimeException('cannot access'),
                fn () => new RuntimeException('duplicate'),
                fn () => new RuntimeException('mass assignment'),
            );
            $this->fail('The missing column was not reported.');
        } catch (QueryException $exception) {
            $this->assertNotInstanceOf(UniqueConstraintViolationException::class, $exception);
        }
    }

    public function test_what_guardian_decides_is_kept_whatever_the_fillable_attributes_of_the_model(): void
    {
        $user = $this->harness()->create(
            DefaultFillableUser::class,
            ['name' => 'Someone', 'email' => 'fillable@example.com', 'password' => 'x'],
            fn () => new RuntimeException('cannot access'),
            fn () => new RuntimeException('duplicate'),
            fn () => new RuntimeException('mass assignment'),
            ['email_verified_at' => now()],
        );

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_what_the_user_typed_still_follows_the_fillable_attributes_of_the_model(): void
    {
        $user = $this->harness()->create(
            DefaultFillableUser::class,
            ['name' => 'Someone', 'email' => 'typed@example.com', 'password' => 'x', 'can_access' => false],
            fn () => new RuntimeException('cannot access'),
            fn () => new RuntimeException('duplicate'),
            fn () => new RuntimeException('mass assignment'),
        );

        $this->assertTrue((bool) $user->fresh()->can_access);
    }
}
