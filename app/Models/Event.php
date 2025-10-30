<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $fillable = [
        'title','description','location','start_at','end_at','is_all_day','is_public'
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at'   => 'datetime',
        'is_all_day' => 'boolean',
        'is_public'  => 'boolean',
    ];
}
