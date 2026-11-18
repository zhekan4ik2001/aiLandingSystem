<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Route extends Model
{
    protected $fillable = [
        'name',
        'point_ids',
    ];

    protected $casts = [
        'point_ids' => 'array',
    ];

    /**
     * Define relation-like query for route points.
     */
    public function points(): HasMany
    {
        return $this->hasMany(Point::class, 'id', 'id')
            ->whereIn('id', $this->point_ids ?? []);
    }

    /**
     * Resolve ordered points for the route.
     */
    public function orderedPoints()
    {
        $ids = $this->point_ids ?? [];

        if (empty($ids)) {
            return collect();
        }

        $points = Point::whereIn('id', $ids)->get()->keyBy('id');

        return collect($ids)
            ->map(static fn ($id) => $points->get($id))
            ->filter()
            ->values();
    }
}
