<?php

namespace App\Jobs;

use App\Enums\SpotCategory;
use App\Models\TravelSpot;
use App\Models\User;
use App\Services\ImageStorageService;
use App\Services\OgMetaService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ImportTravelSpotsJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, array<string, mixed>>  $spots
     */
    public function __construct(
        public readonly int $userId,
        public readonly array $spots,
    ) {}

    public function handle(ImageStorageService $images, OgMetaService $ogMeta): void
    {
        $user = User::findOrFail($this->userId);
        $validCategories = collect(SpotCategory::cases())->map(fn ($c) => $c->value)->all();

        foreach ($this->spots as $spot) {
            $title = isset($spot['title']) && is_string($spot['title']) ? trim($spot['title']) : null;

            if (! $title) {
                continue;
            }

            $category = isset($spot['category']) && in_array($spot['category'], $validCategories)
                ? $spot['category']
                : SpotCategory::Other->value;

            $mapsUrl = isset($spot['maps_url']) && is_string($spot['maps_url']) ? $spot['maps_url'] : null;

            $imageUrl = $mapsUrl ? ($ogMeta->fetch($mapsUrl)['image'] ?? null) : null;
            $storedImage = $imageUrl ? ($images->downloadAndStore($imageUrl) ?? $imageUrl) : null;

            $user->travelSpots()->create([
                'title' => $title,
                'address' => isset($spot['address']) && is_string($spot['address']) ? $spot['address'] : null,
                'category' => $category,
                'maps_url' => $mapsUrl,
                'preview_image' => $storedImage,
                'lat' => isset($spot['lat']) && is_numeric($spot['lat']) ? $spot['lat'] : null,
                'lng' => isset($spot['lng']) && is_numeric($spot['lng']) ? $spot['lng'] : null,
                'visit_time' => isset($spot['visit_time']) && is_string($spot['visit_time']) ? $spot['visit_time'] : null,
                'is_visited' => isset($spot['is_visited']) && (bool) $spot['is_visited'],
            ]);
        }
    }
}