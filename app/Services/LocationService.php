<?php

namespace App\Services;

use App\Enums\GeocodingStatus;
use App\Models\Company;
use App\Models\Lead;
use App\Services\Geocoding\GeocodingResult;
use Illuminate\Support\Facades\Log;
use Throwable;

class LocationService
{
    public function __construct(
        private readonly GeocodingService $geocodingService,
        private readonly DistanceService $distanceService,
    ) {}

    public function distanceKm(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2,
    ): float {
        return $this->distanceService->haversineKm($lat1, $lon1, $lat2, $lon2);
    }

    public function composeLeadAddress(Lead $lead): string
    {
        return $this->composeParts([
            $lead->address_line_1,
            $lead->address_line_2,
            $lead->postcode,
            $lead->city,
            $lead->country ?: 'ES',
        ]);
    }

    public function composeCompanyAddress(Company $company): string
    {
        return $this->composeParts([
            $company->address,
            $company->postcode,
            $company->city,
            $company->country ?: 'ES',
        ]);
    }

    /**
     * Geocode a lead safely — never throws into the caller flow.
     */
    public function geocodeLead(Lead $lead): Lead
    {
        try {
            $address = $this->composeLeadAddress($lead);
            if ($address === '') {
                $lead->forceFill([
                    'geocoding_status' => GeocodingStatus::Failed,
                    'geocoding_error' => __('rml.location.errors.invalid_address'),
                    'geocoded_at' => now(),
                ])->save();

                return $lead->fresh() ?? $lead;
            }

            $lead->forceFill([
                'geocoding_status' => GeocodingStatus::Pending,
                'geocoding_error' => null,
            ])->save();

            $result = $this->geocodingService->geocode($address, $lead->country ?: 'ES');
            $this->applyLeadResult($lead, $result);

            return $lead->fresh() ?? $lead;
        } catch (Throwable $e) {
            Log::error('Lead geocoding failed unexpectedly', [
                'lead_id' => $lead->id,
                'message' => $e->getMessage(),
            ]);

            try {
                $lead->forceFill([
                    'geocoding_status' => GeocodingStatus::Failed,
                    'geocoding_error' => __('rml.location.errors.request_failed'),
                    'geocoded_at' => now(),
                ])->save();
            } catch (Throwable) {
                // ignore secondary save failures
            }

            return $lead->fresh() ?? $lead;
        }
    }

    /**
     * Geocode a company safely — never throws into the caller flow.
     */
    public function geocodeCompany(Company $company): Company
    {
        try {
            $address = $this->composeCompanyAddress($company);
            if ($address === '') {
                $company->forceFill([
                    'geocoding_status' => GeocodingStatus::Failed,
                    'geocoding_error' => __('rml.location.errors.invalid_address'),
                    'geocoded_at' => now(),
                ])->save();

                return $company->fresh() ?? $company;
            }

            $company->forceFill([
                'geocoding_status' => GeocodingStatus::Pending,
                'geocoding_error' => null,
            ])->save();

            $result = $this->geocodingService->geocode($address, $company->country ?: 'ES');
            $this->applyCompanyResult($company, $result);

            return $company->fresh() ?? $company;
        } catch (Throwable $e) {
            Log::error('Company geocoding failed unexpectedly', [
                'company_id' => $company->id,
                'message' => $e->getMessage(),
            ]);

            try {
                $company->forceFill([
                    'geocoding_status' => GeocodingStatus::Failed,
                    'geocoding_error' => __('rml.location.errors.request_failed'),
                    'geocoded_at' => now(),
                ])->save();
            } catch (Throwable) {
                // ignore secondary save failures
            }

            return $company->fresh() ?? $company;
        }
    }

    public function applyManualLeadCoordinates(Lead $lead, float $latitude, float $longitude): Lead
    {
        $lead->forceFill([
            'latitude' => round($latitude, 7),
            'longitude' => round($longitude, 7),
            'geocoding_status' => GeocodingStatus::ManuallyCorrected,
            'geocoding_error' => null,
            'geocoded_at' => now(),
        ])->save();

        return $lead->fresh() ?? $lead;
    }

    public function applyManualCompanyCoordinates(Company $company, float $latitude, float $longitude): Company
    {
        $company->forceFill([
            'latitude' => round($latitude, 7),
            'longitude' => round($longitude, 7),
            'geocoding_status' => GeocodingStatus::ManuallyCorrected,
            'geocoding_error' => null,
            'geocoded_at' => now(),
        ])->save();

        return $company->fresh() ?? $company;
    }

    public function applyLeadResult(Lead $lead, GeocodingResult $result): void
    {
        if ($result->isSuccessful()) {
            $lead->forceFill([
                'latitude' => round((float) $result->latitude, 7),
                'longitude' => round((float) $result->longitude, 7),
                'formatted_address' => $result->formattedAddress,
                'geocoding_status' => GeocodingStatus::Successful,
                'geocoding_error' => null,
                'geocoded_at' => now(),
            ])->save();

            return;
        }

        $lead->forceFill([
            'geocoding_status' => GeocodingStatus::Failed,
            'geocoding_error' => $result->error,
            'geocoded_at' => now(),
        ])->save();
    }

    public function applyCompanyResult(Company $company, GeocodingResult $result): void
    {
        if ($result->isSuccessful()) {
            $company->forceFill([
                'latitude' => round((float) $result->latitude, 7),
                'longitude' => round((float) $result->longitude, 7),
                'formatted_address' => $result->formattedAddress,
                'geocoding_status' => GeocodingStatus::Successful,
                'geocoding_error' => null,
                'geocoded_at' => now(),
            ])->save();

            return;
        }

        $company->forceFill([
            'geocoding_status' => GeocodingStatus::Failed,
            'geocoding_error' => $result->error,
            'geocoded_at' => now(),
        ])->save();
    }

    /**
     * @return array<string, mixed>
     */
    public function leadLocationPayload(Lead $lead): array
    {
        return [
            'latitude' => $lead->latitude !== null ? (float) $lead->latitude : null,
            'longitude' => $lead->longitude !== null ? (float) $lead->longitude : null,
            'formatted_address' => $lead->formatted_address,
            'geocoding_status' => $lead->geocoding_status?->value,
            'geocoded_at' => $lead->geocoded_at?->toIso8601String(),
            'geocoding_error' => $lead->geocoding_error,
            'cadastral_reference' => $lead->cadastral_reference,
            'cadastral_lookup_status' => $lead->cadastral_lookup_status?->value,
            'cadastral_verified_at' => $lead->cadastral_verified_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function companyLocationPayload(Company $company): array
    {
        return [
            'latitude' => $company->latitude !== null ? (float) $company->latitude : null,
            'longitude' => $company->longitude !== null ? (float) $company->longitude : null,
            'formatted_address' => $company->formatted_address,
            'geocoding_status' => $company->geocoding_status?->value,
            'geocoded_at' => $company->geocoded_at?->toIso8601String(),
            'geocoding_error' => $company->geocoding_error,
        ];
    }

    /**
     * @param  list<string|null>  $parts
     */
    private function composeParts(array $parts): string
    {
        $clean = [];
        foreach ($parts as $part) {
            $value = trim((string) $part);
            if ($value !== '') {
                $clean[] = $value;
            }
        }

        return implode(', ', $clean);
    }
}
