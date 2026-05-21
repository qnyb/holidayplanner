<?php

namespace Database\Factories;

use App\Enums\SpotCategory;
use App\Models\TravelSpot;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TravelSpot>
 */
class TravelSpotFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->city(),
            'category' => fake()->randomElement(SpotCategory::cases()),
            'maps_url' => 'https://maps.google.com/?q=' . fake()->latitude() . ',' . fake()->longitude(),
            'preview_image' => fake()->imageUrl(800, 450),
            'lat' => fake()->latitude(),
            'lng' => fake()->longitude(),
            'visit_time' => fake()->dateTimeBetween('now', '+1 year'),
            'is_visited' => false,
        ];
    }

    public function visited(): static
    {
        return $this->state(['is_visited' => true]);
    }
}
