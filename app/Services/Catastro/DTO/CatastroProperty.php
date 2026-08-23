<?php

namespace App\Services\Catastro\DTO;

final class CatastroProperty
{
    /**
     * @param  array<string, mixed>|null  $geometry
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $cadastralReference,
        public readonly ?string $cadastralAddress = null,
        public readonly ?string $province = null,
        public readonly ?string $municipality = null,
        public readonly ?string $propertyUse = null,
        public readonly ?float $constructedAreaM2 = null,
        public readonly ?float $parcelAreaM2 = null,
        public readonly ?int $constructionYear = null,
        public readonly ?string $streetType = null,
        public readonly ?string $streetName = null,
        public readonly ?string $streetNumber = null,
        public readonly ?string $block = null,
        public readonly ?string $staircase = null,
        public readonly ?string $floor = null,
        public readonly ?string $door = null,
        public readonly ?string $postcode = null,
        public readonly ?string $unitLabel = null,
        public readonly ?array $geometry = null,
        public readonly array $raw = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'cadastral_reference' => $this->cadastralReference,
            'cadastral_address' => $this->cadastralAddress,
            'province' => $this->province,
            'municipality' => $this->municipality,
            'property_use' => $this->propertyUse,
            'constructed_area_m2' => $this->constructedAreaM2,
            'parcel_area_m2' => $this->parcelAreaM2,
            'construction_year' => $this->constructionYear,
            'street_type' => $this->streetType,
            'street_name' => $this->streetName,
            'street_number' => $this->streetNumber,
            'block' => $this->block,
            'staircase' => $this->staircase,
            'floor' => $this->floor,
            'door' => $this->door,
            'postcode' => $this->postcode,
            'unit_label' => $this->unitLabel,
            'geometry' => $this->geometry,
        ];
    }
}
