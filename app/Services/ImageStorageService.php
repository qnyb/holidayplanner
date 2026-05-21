<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

readonly class ImageStorageService
{
    public function __construct(private Client $client) {}

    /**
     * Downloads an external image URL and stores it locally.
     * Returns the public storage URL, or null on failure.
     */
    public function downloadAndStore(string $url, string $directory = 'travel-spots'): ?string
    {
        if ($this->isLocalUrl($url)) {
            return $url;
        }

        try {
            $response = $this->client->get($url, [
                'timeout' => 10,
                'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; HolidayPlanner/1.0)'],
            ]);

            $contentType = $response->getHeaderLine('Content-Type');
            $extension = $this->extensionFromContentType($contentType)
                ?? $this->extensionFromUrl($url)
                ?? 'jpg';

            $filename = $directory . '/' . Str::uuid() . '.' . $extension;

            Storage::disk('public')->put($filename, (string) $response->getBody());

            return Storage::disk('public')->url($filename);
        } catch (GuzzleException|Throwable) {
            return null;
        }
    }

    /**
     * Deletes a previously stored local image by its public URL.
     */
    public function delete(string $url): void
    {
        $path = $this->urlToStoragePath($url);

        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    private function isLocalUrl(string $url): bool
    {
        return str_starts_with($url, '/storage/') || str_starts_with($url, url('/storage/'));
    }

    private function urlToStoragePath(string $url): ?string
    {
        $prefix = url('/storage/');

        if (str_starts_with($url, $prefix)) {
            return ltrim(substr($url, strlen($prefix)), '/');
        }

        if (str_starts_with($url, '/storage/')) {
            return ltrim(substr($url, strlen('/storage/')), '/');
        }

        return null;
    }

    private function extensionFromContentType(string $contentType): ?string
    {
        return match (true) {
            str_contains($contentType, 'jpeg'), str_contains($contentType, 'jpg') => 'jpg',
            str_contains($contentType, 'png') => 'png',
            str_contains($contentType, 'webp') => 'webp',
            str_contains($contentType, 'gif') => 'gif',
            default => null,
        };
    }

    private function extensionFromUrl(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! $path) {
            return null;
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif']) ? $ext : null;
    }
}
