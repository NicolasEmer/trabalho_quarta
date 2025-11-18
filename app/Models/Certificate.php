<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Certificate extends Model
{
    protected $table = 'certificates';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'user_name',
        'user_cpf',
        'event_id',
        'event_title',
        'event_start_at',
        'code',
        'issued_at',
        'pdf_url',
    ];

    protected $casts = [
        'event_start_at' => 'datetime',
        'issued_at'      => 'datetime',
    ];
}
