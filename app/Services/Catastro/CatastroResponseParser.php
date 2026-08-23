<?php

namespace App\Services\Catastro;

use App\Enums\CatastroProvider;
use App\Enums\CatastroVerificationStatus;
use App\Services\Catastro\DTO\CatastroLookupResult;
use App\Services\Catastro\DTO\CatastroProperty;
use DOMDocument;
use DOMElement;
use Throwable;

final class CatastroResponseParser
{
    public function parseXml(string $xml): CatastroLookupResult
    {
        if (trim($xml) === '' || preg_match('/<!DOCTYPE|<!ENTITY/i', $xml) === 1) {
            return $this->failed('Malformed Catastro response.');
        }

        $previous = libxml_use_internal_errors(true);

        try {
            $document = new DOMDocument;
            $document->resolveExternals = false;
            $document->substituteEntities = false;

            if (! $document->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT)) {
                return $this->failed('Malformed Catastro response.');
            }

            $root = $document->documentElement;
            if (! $root instanceof DOMElement) {
                return $this->failed('Malformed Catastro response.');
            }

            return $this->parseDnprc([
                $root->localName => $this->elementToArray($root),
            ]);
        } catch (Throwable) {
            return $this->failed('Malformed Catastro response.');
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function parseDnprc(array $payload): CatastroLookupResult
    {
        $result = $this->unwrapResult($payload);

        if (! is_array($result)) {
            return $this->failed('Malformed Catastro response.');
        }

        if (isset($result['lerr'])) {
            return $this->fromError($result['lerr']);
        }

        $control = $result['control'] ?? [];
        $count = (int) ($control['cudnp'] ?? 0);

        if ($count === 0 && ! isset($result['bico']) && ! isset($result['lrcdnp'])) {
            return new CatastroLookupResult(
                status: CatastroVerificationStatus::PropertyNotFound,
                provider: CatastroProvider::National,
                message: 'Property not found.',
                raw: $payload,
            );
        }

        $properties = [];

        if (isset($result['bico'])) {
            $bico = $result['bico'];
            if (isset($bico[0]) && is_array($bico[0])) {
                foreach ($bico as $item) {
                    if (is_array($item)) {
                        $properties[] = $this->parseBico($item);
                    }
                }
            } elseif (is_array($bico)) {
                $properties[] = $this->parseBico($bico);
            }
        }

        if ($properties === [] && isset($result['lrcdnp']['rcdnp'])) {
            $list = $result['lrcdnp']['rcdnp'];
            $items = isset($list[0]) ? $list : [$list];
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $rc = $item['rc'] ?? [];
                $ref = $this->composeReference($rc);
                if ($ref === null) {
                    continue;
                }
                $properties[] = new CatastroProperty(
                    cadastralReference: $ref,
                    cadastralAddress: $item['dt']['ldt'] ?? ($item['ldt'] ?? null),
                    province: $item['dt']['np'] ?? null,
                    municipality: $item['dt']['nm'] ?? null,
                    propertyUse: $item['debi']['luso'] ?? null,
                    constructedAreaM2: isset($item['debi']['sfc']) ? (float) str_replace(',', '.', (string) $item['debi']['sfc']) : null,
                    floor: $item['dt']['locs']['lous']['lourb']['loint']['pt'] ?? null,
                    door: $item['dt']['locs']['lous']['lourb']['loint']['pu'] ?? null,
                    raw: $item,
                );
            }
        }

        if ($properties === []) {
            return new CatastroLookupResult(
                status: CatastroVerificationStatus::PropertyNotFound,
                provider: CatastroProvider::National,
                message: 'Property not found.',
                raw: $payload,
            );
        }

        if (count($properties) > 1) {
            return new CatastroLookupResult(
                status: CatastroVerificationStatus::MultiplePropertiesFound,
                provider: CatastroProvider::National,
                properties: $properties,
                message: 'Multiple cadastral properties found. Select the correct unit.',
                raw: $payload,
            );
        }

        return new CatastroLookupResult(
            status: CatastroVerificationStatus::ManualReviewRequired,
            provider: CatastroProvider::National,
            properties: $properties,
            raw: $payload,
        );
    }

    /**
     * @param  array<string, mixed>  $bico
     */
    private function parseBico(array $bico): CatastroProperty
    {
        $bi = $bico['bi'] ?? $bico;
        $rc = $bi['idbi']['rc'] ?? [];
        $dt = $bi['dt'] ?? [];
        $dir = $dt['locs']['lous']['lourb']['dir'] ?? [];
        $loint = $dt['locs']['lous']['lourb']['loint'] ?? [];
        $debi = $bi['debi'] ?? [];
        $finca = $bico['finca'] ?? [];

        $constructed = isset($debi['sfc'])
            ? (float) str_replace(',', '.', (string) $debi['sfc'])
            : null;
        $parcel = isset($finca['dff']['ss'])
            ? (float) str_replace(',', '.', (string) $finca['dff']['ss'])
            : null;
        $year = isset($debi['ant']) ? (int) $debi['ant'] : null;

        return new CatastroProperty(
            cadastralReference: $this->composeReference($rc) ?? '',
            cadastralAddress: $bi['ldt'] ?? ($finca['ldt'] ?? null),
            province: $dt['np'] ?? null,
            municipality: $dt['nm'] ?? null,
            propertyUse: $debi['luso'] ?? null,
            constructedAreaM2: $constructed,
            parcelAreaM2: $parcel,
            constructionYear: $year > 0 ? $year : null,
            streetType: $dir['tv'] ?? null,
            streetName: $dir['nv'] ?? null,
            streetNumber: isset($dir['pnp']) ? (string) $dir['pnp'] : null,
            block: $loint['bq'] ?? null,
            staircase: $loint['es'] ?? null,
            floor: $loint['pt'] ?? null,
            door: $loint['pu'] ?? null,
            postcode: isset($dt['locs']['lous']['lourb']['dp'])
                ? (string) $dt['locs']['lous']['lourb']['dp']
                : null,
            unitLabel: trim(implode(' ', array_filter([
                isset($loint['pt']) ? 'Planta '.$loint['pt'] : null,
                isset($loint['pu']) ? 'Puerta '.$loint['pu'] : null,
            ]))) ?: null,
            geometry: isset($finca['infgraf']['igraf'])
                ? ['map_url' => $finca['infgraf']['igraf']]
                : null,
            raw: $bico,
        );
    }

    /**
     * @param  array<string, mixed>  $rc
     */
    private function composeReference(array $rc): ?string
    {
        $pc1 = (string) ($rc['pc1'] ?? '');
        $pc2 = (string) ($rc['pc2'] ?? '');
        if ($pc1 === '' || $pc2 === '') {
            return null;
        }

        $car = (string) ($rc['car'] ?? '');
        $cc1 = (string) ($rc['cc1'] ?? '');
        $cc2 = (string) ($rc['cc2'] ?? '');

        return strtoupper($pc1.$pc2.$car.$cc1.$cc2);
    }

    /**
     * @param  mixed  $lerr
     */
    private function fromError(mixed $lerr): CatastroLookupResult
    {
        if (is_array($lerr) && isset($lerr['err'])) {
            $lerr = $lerr['err'];
        }

        $errors = is_array($lerr) ? (isset($lerr[0]) ? $lerr : [$lerr]) : [];
        $code = (string) ($errors[0]['cod'] ?? '');
        $message = (string) ($errors[0]['des'] ?? 'Catastro lookup failed.');

        if (in_array($code, ['11', '12', '43'], true) || str_contains(strtolower($message), 'no existe')) {
            return new CatastroLookupResult(
                status: CatastroVerificationStatus::PropertyNotFound,
                provider: CatastroProvider::National,
                message: $message,
            );
        }

        return $this->failed($message);
    }

    private function failed(string $message): CatastroLookupResult
    {
        return new CatastroLookupResult(
            status: CatastroVerificationStatus::LookupFailed,
            provider: CatastroProvider::National,
            message: $message,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|mixed
     */
    private function unwrapResult(array $payload): mixed
    {
        foreach ([
            'consulta_dnprcResult',
            'consulta_dnplocResult',
            'consulta_dnp',
            'consulta_dnprc',
            'consulta_dnploc',
        ] as $root) {
            if (array_key_exists($root, $payload)) {
                return $payload[$root];
            }
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>|string
     */
    private function elementToArray(DOMElement $element): array|string
    {
        $children = [];

        foreach ($element->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            $name = $child->localName;
            $value = $this->elementToArray($child);

            if (! array_key_exists($name, $children)) {
                $children[$name] = $value;

                continue;
            }

            if (! is_array($children[$name]) || ! array_is_list($children[$name])) {
                $children[$name] = [$children[$name]];
            }

            $children[$name][] = $value;
        }

        return $children === [] ? trim($element->textContent) : $children;
    }
}
