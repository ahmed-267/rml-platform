<?php

namespace App\Services;

use App\Services\Geocoding\GeocodingResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GeocodingService
{
    /**
     * Convert a free-text address into coordinates via Google Geocoding API.
     * Never throws for expected API/business failures — returns a failed result.
     */
    public function geocode(string $address, ?string $country = null): GeocodingResult
    {
        $address = trim($address);
        if ($address === '') {
            return GeocodingResult::invalidAddress();
        }

        $apiKey = trim((string) config('services.google_maps.server_key'));
        if ($apiKey === '') {
            return GeocodingResult::missingKey();
        }

        $country = strtoupper(trim((string) ($country ?: config('services.google_maps.country', 'ES'))));
        if ($country === '') {
            $country = 'ES';
        }

        try {
            $response = Http::timeout(8)
                ->retry(1, 200)
                ->get('https://maps.googleapis.com/maps/api/geocode/json', [
                    'address' => $address,
                    'components' => 'country:'.$country,
                    'region' => strtolower($country),
                    'key' => $apiKey,
                ]);

            if ($response->status() === 429) {
                return GeocodingResult::apiError(__('rml.location.errors.rate_limited'));
            }

            if (! $response->successful()) {
                return GeocodingResult::apiError(__('rml.location.errors.request_failed'));
            }

            $payload = $response->json();
            if (! is_array($payload)) {
                return GeocodingResult::apiError(__('rml.location.errors.request_failed'));
            }

            $status = (string) ($payload['status'] ?? '');

            return match ($status) {
                'OK' => $this->parseSuccess($payload),
                'ZERO_RESULTS' => GeocodingResult::noResults(),
                'INVALID_REQUEST' => GeocodingResult::invalidAddress(),
                'OVER_DAILY_LIMIT', 'OVER_QUERY_LIMIT' => GeocodingResult::apiError(__('rml.location.errors.rate_limited')),
                'REQUEST_DENIED' => GeocodingResult::apiError(
                    (string) ($payload['error_message'] ?? __('rml.location.errors.request_denied'))
                ),
                'UNKNOWN_ERROR' => GeocodingResult::apiError(__('rml.location.errors.request_failed')),
                default => GeocodingResult::apiError(
                    (string) ($payload['error_message'] ?? __('rml.location.errors.request_failed'))
                ),
            };
        } catch (ConnectionException|RequestException $e) {
            Log::warning('Geocoding request failed', ['message' => $e->getMessage()]);

            return GeocodingResult::apiError(__('rml.location.errors.request_failed'));
        } catch (Throwable $e) {
            Log::error('Geocoding unexpected failure', [
                'message' => $e->getMessage(),
            ]);

            return GeocodingResult::apiError(__('rml.location.errors.request_failed'));
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function parseSuccess(array $payload): GeocodingResult
    {
        $results = $payload['results'] ?? null;
        if (! is_array($results) || $results === []) {
            return GeocodingResult::noResults();
        }

        $first = $results[0];
        if (! is_array($first)) {
            return GeocodingResult::noResults();
        }

        $location = data_get($first, 'geometry.location');
        $lat = is_array($location) ? ($location['lat'] ?? null) : null;
        $lng = is_array($location) ? ($location['lng'] ?? null) : null;

        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return GeocodingResult::noResults();
        }

        $formatted = isset($first['formatted_address']) && is_string($first['formatted_address'])
            ? $first['formatted_address']
            : null;

        return GeocodingResult::success((float) $lat, (float) $lng, $formatted);
    }
}
