<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class Airplane extends Model
{
    protected $fillable = [
        'latitude',
        'longitude',
        'altitude_feet',
        'direction',
        'speed',
        'route_id',
        'current_point_index',
        'is_active',
        'scenario',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get route assigned to the airplane.
     */
    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }
}
