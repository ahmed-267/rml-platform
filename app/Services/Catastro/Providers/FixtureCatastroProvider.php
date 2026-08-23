<?php

namespace App\Services\Catastro\Providers;

use App\Enums\CatastroProvider;
use App\Enums\CatastroVerificationStatus;
use App\Services\Catastro\Contracts\CatastroProviderInterface;
use App\Services\Catastro\DTO\CatastroLookupResult;
use App\Services\Catastro\DTO\CatastroProperty;
use RuntimeException;

/**
 * Deterministic Catastro provider for local/UI/automated testing.
 * Never calls the external national service.
 */
final class FixtureCatastroProvider implements CatastroProviderInterface
{
    /**
     * @var array<string, string>
     */
    private array $referenceMap;

    public function __construct()
    {
        $this->referenceMap = $this->buildReferenceMap();
    }

    public function lookupByReference(string $reference): CatastroLookupResult
    {
        $key = $this->referenceMap[$reference] ?? null;

        if ($key === null) {
            return new CatastroLookupResult(
                status: CatastroVerificationStatus::PropertyNotFound,
                provider: CatastroProvider::National,
                message: 'Fixture Catastro: no fixture mapped for this reference.',
            );
        }

        return $this->loadFixture($key);
    }

    public function lookupByAddress(array $address): CatastroLookupResult
    {
        $province = trim((string) ($address['province'] ?? ''));
        $municipality = trim((string) ($address['municipality'] ?? ''));
        $haystack = mb_strtolower($province.' '.$municipality);

        foreach (['navarra', 'nafarroa', 'álava', 'alava', 'araba', 'gipuzkoa', 'guipúzcoa', 'guipuzcoa', 'bizkaia', 'vizcaya', 'país vasco', 'pais vasco', 'euskadi'] as $needle) {
            if (str_contains($haystack, $needle)) {
                return $this->loadFixture('regional_provider');
            }
        }

        $street = mb_strtolower(trim((string) ($address['street_name'] ?? '')));
        if (str_contains($street, 'mayor') && str_contains(mb_strtolower($municipality), 'toledo')) {
            return $this->loadFixture('multiple_results');
        }

        if ($province === '' || $municipality === '' || $street === '') {
            return new CatastroLookupResult(
                status: CatastroVerificationStatus::LookupFailed,
                provider: CatastroProvider::National,
                message: 'Province, municipality and street name are required.',
            );
        }

        return $this->loadFixture('match_single');
    }

    /**
     * @return array<string, string>
     */
    private function buildReferenceMap(): array
    {
        $map = [];
        $definitions = require database_path('seeders/data/catastro-demo-properties.php');

        foreach (array_merge($definitions['fixtures'] ?? [], $definitions['live'] ?? []) as $row) {
            $ref = $row['cadastral_reference'] ?? null;
            $fixtureKey = $row['fixture_key'] ?? null;

            if (is_string($ref) && $ref !== '' && is_string($fixtureKey) && $fixtureKey !== '') {
                $map[$ref] = $fixtureKey;
            }
        }

        // Live verified reference → success fixture when running in fixture mode.
        $liveMatch = (string) config('services.catastro.demo.match_reference', '2749704YJ0624N0001DI');
        if ($liveMatch !== '') {
            $normalized = strtoupper(preg_replace('/[\s\-\.]/', '', $liveMatch) ?? '');
            if ($normalized !== '') {
                $map[$normalized] = 'live_verified_match';
            }
        }

        $areaReview = (string) config('services.catastro.demo.area_review_reference', '');
        if ($areaReview !== '') {
            $normalized = strtoupper(preg_replace('/[\s\-\.]/', '', $areaReview) ?? '');
            if ($normalized !== '') {
                $map[$normalized] = 'live_verified_match';
            }
        }

        $sold = (string) config('services.catastro.demo.sold_reference', '');
        if ($sold !== '') {
            $normalized = strtoupper(preg_replace('/[\s\-\.]/', '', $sold) ?? '');
            if ($normalized !== '') {
                $map[$normalized] = 'live_verified_match';
            }
        }

        // Official OVC documentation example used by parser/lookup feature tests.
        $map['9872023VH5797S0001WX'] = $map['9872023VH5797S0001WX'] ?? 'ovc_docs_example';

        return $map;
    }

    private function loadFixture(string $key): CatastroLookupResult
    {
        $path = database_path('seeders/data/catastro-fixtures/'.$key.'.json');
        if (! is_file($path)) {
            throw new RuntimeException("Missing Catastro fixture [{$key}] at {$path}");
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $kind = (string) ($payload['kind'] ?? 'single');

        return match ($kind) {
            'service_unavailable' => new CatastroLookupResult(
                status: CatastroVerificationStatus::ServiceUnavailable,
                provider: CatastroProvider::National,
                message: (string) ($payload['message'] ?? 'Catastro service unavailable.'),
                raw: $payload,
            ),
            'regional_provider' => new CatastroLookupResult(
                status: CatastroVerificationStatus::RegionalProviderRequired,
                provider: CatastroProvider::tryFrom((string) ($payload['provider'] ?? '')) ?? CatastroProvider::Navarra,
                message: (string) ($payload['message'] ?? 'Regional provider required.'),
                raw: $payload,
            ),
            'multiple' => new CatastroLookupResult(
                status: CatastroVerificationStatus::MultiplePropertiesFound,
                provider: CatastroProvider::National,
                properties: $this->propertiesFromPayload($payload),
                message: (string) ($payload['message'] ?? 'Multiple cadastral properties found. Select the correct unit.'),
                raw: $payload,
            ),
            default => new CatastroLookupResult(
                status: CatastroVerificationStatus::ManualReviewRequired,
                provider: CatastroProvider::National,
                properties: $this->propertiesFromPayload($payload),
                message: $payload['message'] ?? null,
                raw: $payload,
            ),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<CatastroProperty>
     */
    private function propertiesFromPayload(array $payload): array
    {
        $rows = $payload['properties'] ?? [];
        if (! is_array($rows)) {
            return [];
        }

        $properties = [];
        foreach ($rows as $row) {
            if (! is_array($row) || empty($row['cadastral_reference'])) {
                continue;
            }

            $properties[] = new CatastroProperty(
                cadastralReference: (string) $row['cadastral_reference'],
                cadastralAddress: isset($row['cadastral_address']) ? (string) $row['cadastral_address'] : null,
                province: isset($row['province']) ? (string) $row['province'] : null,
                municipality: isset($row['municipality']) ? (string) $row['municipality'] : null,
                propertyUse: isset($row['property_use']) ? (string) $row['property_use'] : null,
                constructedAreaM2: isset($row['constructed_area_m2']) ? (float) $row['constructed_area_m2'] : null,
                parcelAreaM2: isset($row['parcel_area_m2']) ? (float) $row['parcel_area_m2'] : null,
                constructionYear: isset($row['construction_year']) ? (int) $row['construction_year'] : null,
                streetType: isset($row['street_type']) ? (string) $row['street_type'] : null,
                streetName: isset($row['street_name']) ? (string) $row['street_name'] : null,
                streetNumber: isset($row['street_number']) ? (string) $row['street_number'] : null,
                block: isset($row['block']) ? (string) $row['block'] : null,
                staircase: isset($row['staircase']) ? (string) $row['staircase'] : null,
                floor: isset($row['floor']) ? (string) $row['floor'] : null,
                door: isset($row['door']) ? (string) $row['door'] : null,
                postcode: isset($row['postcode']) ? (string) $row['postcode'] : null,
                unitLabel: isset($row['unit_label']) ? (string) $row['unit_label'] : null,
                geometry: is_array($row['geometry'] ?? null) ? $row['geometry'] : null,
                raw: $row,
            );
        }

        return $properties;
    }
}
