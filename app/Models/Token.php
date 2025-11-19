<?php

namespace App\Models;

use App\Models\Traits\UsesPrimaryDatabase;
use Laravel\Sanctum\PersonalAccessToken;

class Token extends PersonalAccessToken
{
    use UsesPrimaryDatabase;
}
