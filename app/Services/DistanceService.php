<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Lead;

class DistanceService
{
    /**
     * MVP: prefer stored lead distance_km.
     * Abstraction kept for future geocoding / maps providers.
     */
    public function forLead(Lead $lead, ?Company $buyerCompany = null): ?float
    {
        if ($lead->distance_km !== null) {
            return (float) $lead->distance_km;
        }

        if (
            $buyerCompany
            && $lead->latitude !== null
            && $lead->longitude !== null
            && $buyerCompany->latitude !== null
            && $buyerCompany->longitude !== null
        ) {
            return $this->haversineKm(
                (float) $buyerCompany->latitude,
                (float) $buyerCompany->longitude,
                (float) $lead->latitude,
                (float) $lead->longitude,
            );
        }

        return null;
    }

    /**
     * Straight-line distance in kilometres between two WGS84 coordinates.
     */
    public function betweenKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        return $this->haversineKm($lat1, $lon1, $lat2, $lon2);
    }

    public function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadiusKm = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadiusKm * $c, 2);
    }
}
