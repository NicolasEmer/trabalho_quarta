<?php

namespace App\Models;

use App\Models\Traits\UsesPrimaryDatabase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Certificate extends Model
{
    use SoftDeletes;


    use UsesPrimaryDatabase;
    protected $table = 'certificates';

    public $timestamps = true;

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
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
        'deleted_at'     => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
