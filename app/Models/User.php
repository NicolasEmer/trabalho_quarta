<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{

    use HasApiTokens, Notifiable;
    use SoftDeletes;
    protected $fillable = [
        'name',
        'email',
        'cpf',
        'phone',
        'completed',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'completed' => 'boolean',
    ];

    public function setCpfAttribute($value): void
    {
        $digits = preg_replace('/\D+/', '', (string) $value);
        $this->attributes['cpf'] = $digits;
    }

    public function registrations()
    {
        return $this->hasMany(\App\Models\EventRegistration::class);
    }
}
