<?php

namespace App\Http\Requests\TravelSpot;

use App\Enums\SpotCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTravelSpotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'category' => ['required', Rule::enum(SpotCategory::class)],
            'maps_url' => ['nullable', 'url', 'max:2000'],
            'preview_image' => ['nullable', 'url', 'max:2000'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'visit_time' => ['nullable', 'date'],
            'is_visited' => ['boolean'],
        ];
    }
}
