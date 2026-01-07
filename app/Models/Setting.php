<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Setting extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'key',
        'value',
    ];

    // Note: store() relationship removed - tenant DB is already scoped to one store
}
