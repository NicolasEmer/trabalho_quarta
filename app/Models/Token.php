<?php

namespace App\Models;

use App\Models\Traits\UsesPrimaryDatabase;
use Laravel\Sanctum\PersonalAccessToken;

class Token extends PersonalAccessToken
{
    use UsesPrimaryDatabase;

    // Garantimos explicitamente o nome da tabela do Sanctum
    protected $table = 'personal_access_tokens';
}
