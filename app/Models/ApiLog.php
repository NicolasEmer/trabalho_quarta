<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiLog extends Model
{
    protected $table = 'api_logs';

    protected $fillable = [
        'direction',
        'service',
        'method',
        'path',
        'status_code',
        'user_id',
        'ip',
        'request_body',
        'response_body',
        'duration_ms',
    ];

    protected $casts = [
        'request_body'  => 'array',
        'response_body' => 'array',
        'duration_ms'   => 'float',
    ];
}
