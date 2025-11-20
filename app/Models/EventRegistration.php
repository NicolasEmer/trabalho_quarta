<?php

namespace App\Models;

use App\Models\Traits\UsesPrimaryDatabase;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventRegistration extends Model
{
    use SoftDeletes;
    use UsesPrimaryDatabase;
    protected $fillable = ['event_id','user_id','status','presence_at'];

    protected $casts = [
        'presence_at' => 'datetime',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
