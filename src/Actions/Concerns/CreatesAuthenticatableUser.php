<?php

namespace Datalogix\Guardian\Actions\Concerns;

use Closure;
use Datalogix\Guardian\Actions\SendEmailVerificationNotification;
use Datalogix\Guardian\Exceptions\EmailVerificationThrottledException;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Support\Auth\FrameworkVerificationListener;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

trait CreatesAuthenticatableUser
{
    protected function createAuthenticatableUser(
        string $modelClass,
        array $attributes,
        Closure $cannotAccessException,
        Closure $queryExceptionHandler,
        Closure $massAssignmentExceptionHandler,
        array $guardianAttributes = [],
    ): Model {
        try {
            return Guardian::wrapInDatabaseTransaction(function () use ($modelClass, $attributes, $guardianAttributes, $cannotAccessException) {
                // forceFill: Laravel's default $fillable would silently drop what Guardian sets itself.
                $user = (new $modelClass($attributes))->forceFill($guardianAttributes);

                if (Guardian::cannotAccess($user)) {
                    throw $cannotAccessException();
                }

                $user->save();

                return $user;
            });
        } catch (UniqueConstraintViolationException $exception) {
            report($exception);

            throw $queryExceptionHandler($exception);
        } catch (MassAssignmentException $exception) {
            report($exception);

            throw $massAssignmentExceptionHandler($exception);
        }
    }

    protected function fireUserRegistered(Model $user): void
    {
        event(new Registered($user));

        // Otherwise the user would get the verification e-mail twice.
        if (! FrameworkVerificationListener::isRegistered()) {
            try {
                app(SendEmailVerificationNotification::class)($user);
            } catch (EmailVerificationThrottledException) {
                // The user can ask for it again from the prompt.
            }
        }
    }
}
