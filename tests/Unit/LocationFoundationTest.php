<?php

namespace Tests\Unit;

use App\Enums\GeocodingStatus;
use App\Services\DistanceService;
use App\Services\GeocodingService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LocationFoundationTest extends TestCase
{
    public function test_haversine_distance_between_madrid_and_valencia(): void
    {
        $distance = (new DistanceService)->betweenKm(
            40.4168,
            -3.7038,
            39.4699,
            -0.3763,
        );

        $this->assertGreaterThan(280, $distance);
        $this->assertLessThan(320, $distance);
    }

    public function test_geocoding_returns_failed_when_api_key_missing(): void
    {
        config(['services.google_maps.server_key' => '']);

        $result = (new GeocodingService)->geocode('Calle Gran Vía 28, Madrid');

        $this->assertSame(GeocodingStatus::Failed, $result->status);
        $this->assertNotNull($result->error);
        $this->assertFalse($result->isSuccessful());
    }

    public function test_geocoding_parses_successful_google_response(): void
    {
        config([
            'services.google_maps.server_key' => 'test-key',
            'services.google_maps.country' => 'ES',
        ]);

        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status' => 'OK',
                'results' => [[
                    'formatted_address' => 'Calle de Gran Vía, 28, 28013 Madrid, Spain',
                    'geometry' => [
                        'location' => [
                            'lat' => 40.4203,
                            'lng' => -3.7058,
                        ],
                    ],
                ]],
            ]),
        ]);

        $result = (new GeocodingService)->geocode('Calle Gran Vía 28, Madrid');

        $this->assertTrue($result->isSuccessful());
        $this->assertSame(GeocodingStatus::Successful, $result->status);
        $this->assertEqualsWithDelta(40.4203, (float) $result->latitude, 0.0001);
        $this->assertEqualsWithDelta(-3.7058, (float) $result->longitude, 0.0001);
    }

    public function test_geocoding_handles_zero_results_without_throwing(): void
    {
        config(['services.google_maps.server_key' => 'test-key']);

        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status' => 'ZERO_RESULTS',
                'results' => [],
            ]),
        ]);

        $result = (new GeocodingService)->geocode('Nowhere Street 999');

        $this->assertSame(GeocodingStatus::Failed, $result->status);
        $this->assertNotNull($result->error);
    }

    public function test_geocoding_handles_empty_address(): void
    {
        config(['services.google_maps.server_key' => 'test-key']);

        $result = (new GeocodingService)->geocode('   ');

        $this->assertSame(GeocodingStatus::Failed, $result->status);
    }
}
