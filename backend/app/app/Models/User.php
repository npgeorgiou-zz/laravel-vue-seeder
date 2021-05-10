<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * @property string $username
     * @property string $email
     * @property string $password
     * @property boolean $is_backoffice
     * @property boolean $is_anonymous
     */

    protected $fillable = [
        'username',
        'email',
        'password',
        'is_backoffice',
        'is_anonymous',
    ];

    protected $hidden = [
        'password',
        'token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_backoffice' => 'boolean',
        'is_anonymous' => 'boolean',
    ];

    public function requests() {
        return $this->hasMany(Request::class);
    }

    public function responses() {
        return $this->hasMany(Response::class);
    }
}
