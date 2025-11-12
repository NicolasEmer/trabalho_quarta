<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $fillable = [
        'title','description','location','start_at','end_at','capacity','is_all_day','is_public'
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at'   => 'datetime',
        'is_all_day' => 'boolean',
        'is_public'  => 'boolean',
    ];

    public function registrations()
    {
        return $this->hasMany(\App\Models\EventRegistration::class);
    }

    public function confirmedRegistrationsCount(): int
    {
        return $this->registrations()->where('status','confirmed')->count();
    }

    public function isFull(): bool
    {
        return !is_null($this->capacity ?? null)
            && $this->confirmedRegistrationsCount() >= (int) $this->capacity;
    }
}
