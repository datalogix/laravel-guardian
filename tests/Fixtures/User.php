<?php

namespace Datalogix\Guardian\Tests\Fixtures;

use Datalogix\Guardian\Contracts\FortressUser;
use Datalogix\Guardian\Fortress;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FortressUser, MustVerifyEmailContract
{
    use MustVerifyEmail;
    use Notifiable;

    protected $table = 'users';

    protected $guarded = [];

    protected $attributes = [
        'can_access' => true,
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'can_access' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function canAccessFortress(Fortress $fortress): bool
    {
        return (bool) $this->can_access;
    }
}
