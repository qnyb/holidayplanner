<?php

namespace App\Http\Controllers;

use App\Enums\SpotCategory;
use App\Http\Requests\TravelSpot\StoreTravelSpotRequest;
use App\Http\Requests\TravelSpot\UpdateTravelSpotRequest;
use App\Jobs\ImportTravelSpotsJob;
use App\Models\TravelSpot;
use App\Services\ImageStorageService;
use App\Services\OgMetaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TravelSpotController extends Controller
{
    public function index(Request $request): Response
    {
        $spots = $request->user()
            ->travelSpots()
            ->orderBy('visit_time')
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

        return Inertia::render('TravelSpots/Index', [
            'spots' => $spots,
            'categories' => collect(SpotCategory::cases())->map(fn (SpotCategory $c) => [
                'value' => $c->value,
                'label' => $c->label(),
                'color' => $c->color(),
                'icon' => $c->icon(),
            ]),
        ]);
    }

    public function store(StoreTravelSpotRequest $request, ImageStorageService $images): RedirectResponse
    {
        $data = $request->validated();
        $data['preview_image'] = $this->storeImage($images, $data['preview_image'] ?? null);

        $request->user()->travelSpots()->create($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Mekan eklendi.']);

        return to_route('travel-spots.index');
    }

    public function update(UpdateTravelSpotRequest $request, TravelSpot $travelSpot, ImageStorageService $images): RedirectResponse
    {
        Gate::authorize('update', $travelSpot);

        $data = $request->validated();

        $newImageUrl = $data['preview_image'] ?? null;
        $oldImageUrl = $travelSpot->preview_image;

        $data['preview_image'] = $this->storeImage($images, $newImageUrl);

        // Delete old local image if it was replaced
        if ($oldImageUrl && $oldImageUrl !== $data['preview_image']) {
            $images->delete($oldImageUrl);
        }

        $travelSpot->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Mekan güncellendi.']);

        return to_route('travel-spots.index');
    }

    public function destroy(TravelSpot $travelSpot, ImageStorageService $images): RedirectResponse
    {
        Gate::authorize('delete', $travelSpot);

        if ($travelSpot->preview_image) {
            $images->delete($travelSpot->preview_image);
        }

        $travelSpot->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Mekan silindi.']);

        return to_route('travel-spots.index');
    }

    public function export(Request $request): HttpResponse
    {
        $spots = $request->user()
            ->travelSpots()
            ->orderBy('visit_time')
            ->get()
            ->map(fn (TravelSpot $spot) => [
                'title' => $spot->title,
                'address' => $spot->address,
                'category' => $spot->category->value,
                'maps_url' => $spot->maps_url,
                'preview_image' => $spot->preview_image,
                'lat' => $spot->lat,
                'lng' => $spot->lng,
                'visit_time' => $spot->visit_time?->format('Y-m-d\TH:i:s'),
                'is_visited' => $spot->is_visited,
            ]);

        return response(
            json_encode($spots->values(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            200,
            [
                'Content-Type' => 'application/json',
                'Content-Disposition' => 'attachment; filename="seyahat-planlari.json"',
            ]
        );
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:json', 'max:2048'],
        ]);

        $contents = file_get_contents($request->file('file')->getRealPath());
        $spots = json_decode($contents, true);

        if (! is_array($spots)) {
            return back()->withErrors(['file' => 'Geçersiz JSON formatı.']);
        }

        ImportTravelSpotsJob::dispatch($request->user()->id, $spots);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'İçe aktarma başlatıldı, mekanlar kısa süre içinde eklenecek.']);

        return to_route('travel-spots.index');
    }

    public function fetchMeta(Request $request, OgMetaService $ogMetaService): JsonResponse
    {
        $request->validate(['url' => ['required', 'url']]);

        $meta = $ogMetaService->fetch($request->string('url')->toString());

        return response()->json($meta);
    }

    private function storeImage(ImageStorageService $images, ?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        return $images->downloadAndStore($url) ?? $url;
    }
}
