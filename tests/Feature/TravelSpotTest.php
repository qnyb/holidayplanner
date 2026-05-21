<?php

use App\Enums\SpotCategory;
use App\Models\TravelSpot;
use App\Models\User;
use App\Services\OgMetaService;

use function Pest\Laravel\actingAs;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('renders travel spots index page', function () {
    TravelSpot::factory()->count(3)->create(['user_id' => $this->user->id]);

    actingAs($this->user)
        ->get('/travel-spots')
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('TravelSpots/Index')
                ->has('spots', 3)
                ->has('categories'),
        );
});

it('only shows spots belonging to authenticated user', function () {
    TravelSpot::factory()->count(2)->create(['user_id' => $this->user->id]);
    TravelSpot::factory()->count(3)->create(['user_id' => User::factory()->create()->id]);

    actingAs($this->user)
        ->get('/travel-spots')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('spots', 2));
});

it('requires authentication to view spots', function () {
    $this->get('/travel-spots')->assertRedirect('/login');
});

it('creates a travel spot', function () {
    actingAs($this->user)
        ->post('/travel-spots', [
            'title' => 'Aya Sofya',
            'category' => SpotCategory::Museum->value,
            'maps_url' => 'https://maps.google.com/?q=41.0086,28.9802',
            'preview_image' => null,
            'lat' => '41.0086',
            'lng' => '28.9802',
            'visit_time' => '2026-06-15 10:00:00',
            'is_visited' => false,
        ])
        ->assertRedirect('/travel-spots');

    expect(TravelSpot::where('title', 'Aya Sofya')->exists())->toBeTrue();
});

it('validates required fields when creating a spot', function () {
    actingAs($this->user)
        ->post('/travel-spots', [])
        ->assertSessionHasErrors(['title', 'category']);
});

it('updates a travel spot', function () {
    $spot = TravelSpot::factory()->create(['user_id' => $this->user->id, 'title' => 'Eski Ad']);

    actingAs($this->user)
        ->put("/travel-spots/{$spot->id}", [
            'title' => 'Yeni Ad',
            'category' => SpotCategory::Food->value,
            'is_visited' => true,
        ])
        ->assertRedirect('/travel-spots');

    expect($spot->fresh()->title)->toBe('Yeni Ad')
        ->and($spot->fresh()->is_visited)->toBeTrue();
});

it('cannot update another user\'s spot', function () {
    $otherSpot = TravelSpot::factory()->create(['user_id' => User::factory()->create()->id]);

    actingAs($this->user)
        ->put("/travel-spots/{$otherSpot->id}", [
            'title' => 'Hacker',
            'category' => SpotCategory::Other->value,
            'is_visited' => false,
        ])
        ->assertForbidden();
});

it('deletes a travel spot', function () {
    $spot = TravelSpot::factory()->create(['user_id' => $this->user->id]);

    actingAs($this->user)
        ->delete("/travel-spots/{$spot->id}")
        ->assertRedirect('/travel-spots');

    expect(TravelSpot::find($spot->id))->toBeNull();
});

it('cannot delete another user\'s spot', function () {
    $otherSpot = TravelSpot::factory()->create(['user_id' => User::factory()->create()->id]);

    actingAs($this->user)
        ->delete("/travel-spots/{$otherSpot->id}")
        ->assertForbidden();
});

it('fetches og meta from a url', function () {
    $mock = Mockery::mock(OgMetaService::class);
    $mock->shouldReceive('fetch')
        ->with('https://example.com')
        ->andReturn(['title' => 'Test Mekan', 'image' => 'https://example.com/image.jpg']);

    app()->instance(OgMetaService::class, $mock);

    actingAs($this->user)
        ->postJson('/travel-spots/fetch-meta', ['url' => 'https://example.com'])
        ->assertOk()
        ->assertJson(['title' => 'Test Mekan', 'image' => 'https://example.com/image.jpg']);
});

it('returns google maps og image via facebookexternalhit ua', function () {
    $html = '<html><head>'
        . '<meta property="og:title" content="Aya Sofya">'
        . '<meta property="og:image" content="https://lh3.googleusercontent.com/some-place-photo">'
        . '</head></html>';

    $mock = Mockery::mock(OgMetaService::class);
    $mock->shouldReceive('fetch')
        ->with('https://maps.app.goo.gl/example')
        ->andReturn(['title' => 'Aya Sofya', 'image' => 'https://lh3.googleusercontent.com/some-place-photo']);

    app()->instance(OgMetaService::class, $mock);

    actingAs($this->user)
        ->postJson('/travel-spots/fetch-meta', ['url' => 'https://maps.app.goo.gl/example'])
        ->assertOk()
        ->assertJson(['title' => 'Aya Sofya', 'image' => 'https://lh3.googleusercontent.com/some-place-photo']);
});

it('validates url in fetch-meta endpoint', function () {
    actingAs($this->user)
        ->postJson('/travel-spots/fetch-meta', ['url' => 'not-a-url'])
        ->assertUnprocessable();
});
