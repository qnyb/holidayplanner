<?php

namespace App\Http\Controllers;

use App\Models\TravelSpot;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class TimelineController extends Controller
{
    public function index(): Response
    {
        $spots = TravelSpot::orderBy('visit_time')
            ->get()
            ->map(fn (TravelSpot $spot) => [
                'id' => $spot->id,
                'title' => $spot->title,
                'address' => $spot->address,
                'category' => $spot->category->value,
                'category_label' => $spot->category->label(),
                'category_color' => $spot->category->color(),
                'category_icon' => $spot->category->icon(),
                'maps_url' => $spot->maps_url,
                'preview_image' => $spot->preview_image,
                'lat' => $spot->lat,
                'lng' => $spot->lng,
                'visit_time' => $spot->visit_time?->format('Y-m-d\TH:i:s'),
                'is_visited' => $spot->is_visited,
            ]);

        return Inertia::render('Welcome', ['spots' => $spots]);
    }

    public function toggleVisited(TravelSpot $travelSpot): JsonResponse
    {
        $travelSpot->update(['is_visited' => ! $travelSpot->is_visited]);

        return response()->json(['is_visited' => $travelSpot->is_visited]);
    }
}
