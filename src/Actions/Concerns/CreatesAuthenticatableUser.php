<?php

namespace Datalogix\Guardian\Actions\Concerns;

use Closure;
use Datalogix\Guardian\Actions\SendEmailVerificationNotification;
use Datalogix\Guardian\Guardian;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

trait CreatesAuthenticatableUser
{
    protected function createAuthenticatableUser(
        string $modelClass,
        array $attributes,
        Closure $cannotAccessException,
        Closure $queryExceptionHandler,
        Closure $massAssignmentExceptionHandler,
    ): Model {
        try {
            return Guardian::wrapInDatabaseTransaction(function () use ($modelClass, $attributes, $cannotAccessException) {
                $user = new $modelClass($attributes);

                if (Guardian::cannotAccess($user)) {
                    throw $cannotAccessException();
                }

                $user->save();

                return $user;
            });
        } catch (QueryException $exception) {
            report($exception);

            if ($exception->getCode() !== '23000') {
                throw $exception;
            }

            throw $queryExceptionHandler($exception);
        } catch (MassAssignmentException $exception) {
            report($exception);

            throw $massAssignmentExceptionHandler($exception);
        }
    }

    protected function fireUserRegistered(Model $user): void
    {
        event(new Registered($user));

        app(SendEmailVerificationNotification::class)($user);
    }
}
