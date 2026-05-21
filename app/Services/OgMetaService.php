<?php

namespace App\Services;

use App\Enums\SpotCategory;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RedirectMiddleware;
use Throwable;

readonly class OgMetaService
{
    public function __construct(private Client $client) {}

    /**
     * @return array{title: string|null, address: string|null, image: string|null, description: string|null, lat: float|null, lng: float|null, category: string|null}
     */
    public function fetch(string $url): array
    {
        try {
            $response = $this->client->get($url, [
                'timeout' => 5,
                'allow_redirects' => ['max' => 5, 'track_redirects' => true],
                'headers' => [
                    'User-Agent' => 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)',
                ],
            ]);

            $html = (string) $response->getBody();

            $redirectUrls = $response->getHeader(RedirectMiddleware::HISTORY_HEADER);
            $finalUrl = end($redirectUrls) ?: $url;

            $rawTitle = $this->extractOgTag($html, 'title');
            [$title, $address] = $this->splitTitleAndAddress($rawTitle);

            [$lat, $lng] = $this->extractCoordsFromUrl($finalUrl);

            $description = $this->extractOgTag($html, 'description');
            $placeType = $this->extractPlaceType($description);
            $category = $this->guessCategory($placeType);

            return [
                'title' => $title,
                'address' => $address,
                'image' => $this->extractOgTag($html, 'image'),
                'description' => $description,
                'lat' => $lat,
                'lng' => $lng,
                'category' => $category?->value,
            ];
        } catch (GuzzleException|Throwable) {
            return ['title' => null, 'address' => null, 'image' => null, 'description' => null, 'lat' => null, 'lng' => null, 'category' => null];
        }
    }

    /**
     * Extracts the place type from "★★★★★ · Ukrayna Restoranı" → "Ukrayna Restoranı"
     */
    private function extractPlaceType(?string $description): ?string
    {
        if (! $description) {
            return null;
        }

        $parts = preg_split('/\s*·\s*/u', $description, 2);

        return isset($parts[1]) ? trim($parts[1]) : trim($parts[0]);
    }

    private function guessCategory(?string $placeType): ?SpotCategory
    {
        if (! $placeType) {
            return null;
        }

        $lower = mb_strtolower($placeType);

        $map = [
            'food' => [
                'restoran', 'restaurant', 'kafe', 'cafe', 'kafeterya', 'bistro',
                'yemek', 'food', 'pizza', 'burger', 'döner', 'kebap', 'kebab',
                'bar', 'pub', 'brasserie', 'lokanta', 'meyhane', 'pastane',
                'fırın', 'bakery', 'sushi', 'çay', 'tea house',
            ],
            'museum' => [
                'müze', 'museum', 'galeri', 'gallery', 'sergi', 'exhibition',
                'sanat', 'art', 'tarihi', 'historical', 'arkeoloji',
            ],
            'landmark' => [
                'kilise', 'church', 'cami', 'mosque', 'katedral', 'cathedral',
                'kule', 'tower', 'köprü', 'bridge', 'anıt', 'monument',
                'saray', 'palace', 'kale', 'castle', 'tourist attraction',
                'turistik', 'landmark',
            ],
            'nature' => [
                'park', 'orman', 'forest', 'göl', 'lake', 'nehir', 'river',
                'plaj', 'beach', 'dağ', 'mountain', 'şelale', 'waterfall',
                'bahçe', 'garden', 'botanik', 'botanical', 'milli park', 'national park',
            ],
            'shopping' => [
                'alışveriş', 'shopping', 'market', 'mağaza', 'store', 'shop',
                'çarşı', 'bazaar', 'bazar', 'avm', 'mall', 'outlet',
                'süpermarket', 'supermarket', 'eczane', 'pharmacy',
            ],
            'entertainment' => [
                'sinema', 'cinema', 'tiyatro', 'theatre', 'theater', 'konser',
                'concert', 'eğlence', 'entertainment', 'gece kulübü', 'night club',
                'lunapark', 'amusement', 'bowling', 'escape room',
            ],
            'accommodation' => [
                'otel', 'hotel', 'hostel', 'pansiyon', 'apart', 'villa',
                'konaklama', 'accommodation', 'motel', 'resort', 'inn',
            ],
        ];

        foreach ($map as $value => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($lower, $keyword)) {
                    return SpotCategory::from($value);
                }
            }
        }

        return null;
    }

    /**
     * Splits "Place Name · Street, City, Country" into [title, address].
     *
     * @return array{string|null, string|null}
     */
    private function splitTitleAndAddress(?string $rawTitle): array
    {
        if ($rawTitle === null) {
            return [null, null];
        }

        $parts = preg_split('/\s*·\s*/u', $rawTitle, 2);

        return [
            trim($parts[0]) ?: null,
            isset($parts[1]) ? trim($parts[1]) : null,
        ];
    }

    /**
     * Extracts the most precise lat/lng from a Google Maps URL.
     * Prefers the 3d/4d format (pin location) over the viewport @lat,lng.
     *
     * @return array{float|null, float|null}
     */
    private function extractCoordsFromUrl(string $url): array
    {
        // 3d<lat>!4d<lng>  — exact pin coordinates
        if (preg_match('/!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/', $url, $m)) {
            return [(float) $m[1], (float) $m[2]];
        }

        // /@<lat>,<lng>,<zoom>  — viewport center
        if (preg_match('/@(-?\d+\.\d+),(-?\d+\.\d+)/', $url, $m)) {
            return [(float) $m[1], (float) $m[2]];
        }

        return [null, null];
    }

    private function extractOgTag(string $html, string $property): ?string
    {
        if (preg_match('/<meta[^>]+property=["\']og:' . $property . '["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $html, $matches)) {
            return $matches[1];
        }

        if (preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:' . $property . '["\'][^>]*>/i', $html, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
