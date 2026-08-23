<?php

namespace App\Services\Catastro\Contracts;

use App\Services\Catastro\DTO\CatastroLookupResult;

interface CatastroProviderInterface
{
    public function lookupByReference(string $reference): CatastroLookupResult;

    /**
     * @param  array{
     *     province?: string|null,
     *     municipality?: string|null,
     *     street_type?: string|null,
     *     street_name?: string|null,
     *     street_number?: string|null,
     *     block?: string|null,
     *     staircase?: string|null,
     *     floor?: string|null,
     *     door?: string|null,
     *     postcode?: string|null
     * }  $address
     */
    public function lookupByAddress(array $address): CatastroLookupResult;
}
