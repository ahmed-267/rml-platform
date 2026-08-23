<?php

namespace App\Services\Catastro\Providers;

use App\Enums\CatastroProvider;
use App\Enums\CatastroVerificationStatus;
use App\Services\Catastro\CatastroResponseParser;
use App\Services\Catastro\Contracts\CatastroProviderInterface;
use App\Services\Catastro\DTO\CatastroLookupResult;
use App\Support\CatastroSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

final class NationalCatastroProvider implements CatastroProviderInterface
{
    public function __construct(
        private readonly CatastroResponseParser $parser,
    ) {}

    public function lookupByReference(string $reference): CatastroLookupResult
    {
        if (! CatastroSettings::enabled()) {
            return new CatastroLookupResult(
                status: CatastroVerificationStatus::ServiceUnavailable,
                provider: CatastroProvider::National,
                message: 'Catastro lookup is disabled or unavailable.',
            );
        }

        $base = rtrim((string) config(
            'catastro.base_url',
            config('services.catastro.national_base_url'),
        ), '/');
        $url = $base.'/rest/Consulta_DNPRC';

        $result = $this->request($url, ['RefCat' => $reference]);
        if (! in_array($result->status, [
            CatastroVerificationStatus::ServiceUnavailable,
            CatastroVerificationStatus::LookupFailed,
        ], true)) {
            return $result;
        }

        // Same official non-protected operation, JSON representation. Some
        // Catastro edge nodes intermittently reject the REST XML transport.
        $jsonResult = $this->requestJson($base.'/json/Consulta_DNPRC', [
            'RefCat' => $reference,
        ]);
        if (! in_array($jsonResult->status, [
            CatastroVerificationStatus::ServiceUnavailable,
            CatastroVerificationStatus::LookupFailed,
        ], true)) {
            return $jsonResult;
        }

        $legacy = rtrim((string) config('catastro.legacy_url'), '/');
        if ($legacy === '') {
            return $jsonResult;
        }

        // Legacy public ASMX operation requires all parameters, even empty ones.
        return $this->request($legacy.'/Consulta_DNPRC', [
            'Provincia' => '',
            'Municipio' => '',
            'RC' => $reference,
        ]);
    }

    public function lookupByAddress(array $address): CatastroLookupResult
    {
        if (! CatastroSettings::enabled()) {
            return new CatastroLookupResult(
                status: CatastroVerificationStatus::ServiceUnavailable,
                provider: CatastroProvider::National,
                message: 'Catastro lookup is disabled or unavailable.',
            );
        }

        $province = trim((string) ($address['province'] ?? ''));
        $municipality = trim((string) ($address['municipality'] ?? ''));
        $streetName = trim((string) ($address['street_name'] ?? ''));

        if ($province === '' || $municipality === '' || $streetName === '') {
            return new CatastroLookupResult(
                status: CatastroVerificationStatus::LookupFailed,
                provider: CatastroProvider::National,
                message: 'Province, municipality and street name are required.',
            );
        }

        if ($this->requiresRegionalProvider($province, $municipality)) {
            return new CatastroLookupResult(
                status: CatastroVerificationStatus::RegionalProviderRequired,
                provider: $this->regionalProviderFor($province),
                message: 'National Catastro does not cover this location. Manual or regional verification is required.',
            );
        }

        $base = rtrim((string) config(
            'catastro.base_url',
            config('services.catastro.national_base_url'),
        ), '/');
        $url = $base.'/rest/Consulta_DNPLOC';

        return $this->request($url, array_filter([
            'Provincia' => $province,
            'Municipio' => $municipality,
            'Sigla' => $address['street_type'] ?? null,
            'Calle' => $streetName,
            'Numero' => $address['street_number'] ?? null,
            'Bloque' => $address['block'] ?? null,
            'Escalera' => $address['staircase'] ?? null,
            'Planta' => $address['floor'] ?? null,
            'Puerta' => $address['door'] ?? null,
        ], fn ($value) => $value !== null && $value !== ''));
    }

    /**
     * @param  array<string, scalar>  $query
     */
    private function request(string $url, array $query): CatastroLookupResult
    {
        $timeout = (int) config('catastro.timeout', config('services.catastro.timeout', 10));

        try {
            $response = Http::timeout($timeout)
                ->accept('application/xml')
                ->get($url, $query);

            if (! $response->successful()) {
                return new CatastroLookupResult(
                    status: CatastroVerificationStatus::ServiceUnavailable,
                    provider: CatastroProvider::National,
                    message: 'Catastro service unavailable.',
                    raw: ['status' => $response->status()],
                );
            }

            return $this->parser->parseXml($response->body());
        } catch (ConnectionException) {
            return new CatastroLookupResult(
                status: CatastroVerificationStatus::ServiceUnavailable,
                provider: CatastroProvider::National,
                message: 'Catastro service timed out or is unreachable.',
            );
        } catch (Throwable $e) {
            report($e);

            return new CatastroLookupResult(
                status: CatastroVerificationStatus::LookupFailed,
                provider: CatastroProvider::National,
                message: 'Catastro lookup failed.',
            );
        }
    }

    /**
     * @param  array<string, scalar>  $query
     */
    private function requestJson(string $url, array $query): CatastroLookupResult
    {
        $timeout = (int) config('catastro.timeout', config('services.catastro.timeout', 10));

        try {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->get($url, $query);

            if (! $response->successful()) {
                return new CatastroLookupResult(
                    status: CatastroVerificationStatus::ServiceUnavailable,
                    provider: CatastroProvider::National,
                    message: 'Catastro service unavailable.',
                    raw: ['status' => $response->status()],
                );
            }

            $payload = $response->json();
            if (! is_array($payload)) {
                return new CatastroLookupResult(
                    status: CatastroVerificationStatus::LookupFailed,
                    provider: CatastroProvider::National,
                    message: 'Malformed Catastro response.',
                );
            }

            return $this->parser->parseDnprc($payload);
        } catch (ConnectionException) {
            return new CatastroLookupResult(
                status: CatastroVerificationStatus::ServiceUnavailable,
                provider: CatastroProvider::National,
                message: 'Catastro service timed out or is unreachable.',
            );
        } catch (Throwable $e) {
            report($e);

            return new CatastroLookupResult(
                status: CatastroVerificationStatus::LookupFailed,
                provider: CatastroProvider::National,
                message: 'Catastro lookup failed.',
            );
        }
    }

    private function requiresRegionalProvider(string $province, string $municipality): bool
    {
        $haystack = mb_strtolower($province.' '.$municipality);
        foreach (['navarra', 'nafarroa', 'álava', 'alava', 'araba', 'gipuzkoa', 'guipúzcoa', 'guipuzcoa', 'bizkaia', 'vizcaya', 'país vasco', 'pais vasco', 'euskadi'] as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function regionalProviderFor(string $province): CatastroProvider
    {
        $haystack = mb_strtolower($province);
        if (str_contains($haystack, 'navarra') || str_contains($haystack, 'nafarroa')) {
            return CatastroProvider::Navarra;
        }

        return CatastroProvider::Basque;
    }
}
