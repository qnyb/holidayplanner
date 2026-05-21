<?php

namespace App\Models;

use App\Enums\SpotCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TravelSpot extends Model
{
    /** @use HasFactory<\Database\Factories\TravelSpotFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'address',
        'category',
        'maps_url',
        'preview_image',
        'lat',
        'lng',
        'visit_time',
        'is_visited',
    ];

    protected $casts = [
        'category' => SpotCategory::class,
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
        'visit_time' => 'datetime',
        'is_visited' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
