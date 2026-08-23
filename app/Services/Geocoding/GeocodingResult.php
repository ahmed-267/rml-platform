<?php

namespace App\Services\Geocoding;

use App\Enums\GeocodingStatus;

final class GeocodingResult
{
    public function __construct(
        public readonly GeocodingStatus $status,
        public readonly ?float $latitude = null,
        public readonly ?float $longitude = null,
        public readonly ?string $formattedAddress = null,
        public readonly ?string $error = null,
    ) {}

    public function isSuccessful(): bool
    {
        return $this->status === GeocodingStatus::Successful
            && $this->latitude !== null
            && $this->longitude !== null;
    }

    public static function missingKey(): self
    {
        return new self(
            GeocodingStatus::Failed,
            error: __('rml.location.errors.missing_api_key'),
        );
    }

    public static function invalidAddress(): self
    {
        return new self(
            GeocodingStatus::Failed,
            error: __('rml.location.errors.invalid_address'),
        );
    }

    public static function noResults(): self
    {
        return new self(
            GeocodingStatus::Failed,
            error: __('rml.location.errors.no_results'),
        );
    }

    public static function apiError(string $message): self
    {
        return new self(
            GeocodingStatus::Failed,
            error: $message,
        );
    }

    public static function success(float $latitude, float $longitude, ?string $formattedAddress): self
    {
        return new self(
            GeocodingStatus::Successful,
            latitude: $latitude,
            longitude: $longitude,
            formattedAddress: $formattedAddress,
        );
    }
}
