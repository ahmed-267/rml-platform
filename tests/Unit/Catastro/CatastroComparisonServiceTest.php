<?php

namespace Tests\Unit\Catastro;

use App\Enums\CatastroVerificationStatus;
use App\Models\Lead;
use App\Services\Catastro\CatastroComparisonService;
use App\Services\Catastro\DTO\CatastroProperty;
use App\Services\DistanceService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CatastroComparisonServiceTest extends TestCase
{
    private function service(): CatastroComparisonService
    {
        return new CatastroComparisonService(new DistanceService);
    }

    private function property(array $overrides = []): CatastroProperty
    {
        return new CatastroProperty(
            cadastralReference: $overrides['cadastral_reference'] ?? '9872023VH5797S0001WX',
            cadastralAddress: $overrides['cadastral_address'] ?? 'CL GLORIA 51 13730 SANTA CRUZ DE MUDELA (CIUDAD REAL)',
            province: $overrides['province'] ?? 'CIUDAD REAL',
            municipality: $overrides['municipality'] ?? 'SANTA CRUZ DE MUDELA',
            propertyUse: $overrides['property_use'] ?? 'Residencial',
            constructedAreaM2: $overrides['constructed_area_m2'] ?? 308.0,
            postcode: $overrides['postcode'] ?? '13730',
            streetName: $overrides['street_name'] ?? 'GLORIA',
            streetNumber: $overrides['street_number'] ?? '51',
        );
    }

    #[Test]
    public function matched_when_submitted_data_aligns(): void
    {
        $lead = new Lead([
            'address_line_1' => 'Calle Gloria 51',
            'city' => 'Santa Cruz de Mudela',
            'postcode' => '13730',
            'size_m2' => 308,
            'property_type' => 'detached',
        ]);

        $result = $this->service()->compare($lead, $this->property());

        $this->assertSame(CatastroVerificationStatus::Matched, $result['status']);
        $this->assertSame([], $result['warnings']);
    }

    #[Test]
    public function postcode_mismatch_is_hard_mismatch(): void
    {
        $lead = new Lead([
            'address_line_1' => 'Calle Gloria 51',
            'city' => 'Santa Cruz de Mudela',
            'postcode' => '28001',
            'size_m2' => 308,
            'property_type' => 'detached',
        ]);

        $result = $this->service()->compare($lead, $this->property());

        $this->assertSame(CatastroVerificationStatus::MismatchDetected, $result['status']);
        $this->assertTrue(
            collect($result['warnings'])->contains(fn (array $w) => $w['code'] === 'postcode_mismatch'),
        );
    }

    #[Test]
    public function lead_property_type_used_before_survey_for_use_mismatch(): void
    {
        $lead = new Lead([
            'address_line_1' => 'Calle Gloria 51',
            'city' => 'Santa Cruz de Mudela',
            'postcode' => '13730',
            'size_m2' => 308,
            'property_type' => 'detached',
        ]);

        $result = $this->service()->compare(
            $lead,
            $this->property(['property_use' => 'Industrial']),
        );

        $this->assertSame(CatastroVerificationStatus::MismatchDetected, $result['status']);
        $this->assertTrue(
            collect($result['warnings'])->contains(fn (array $w) => $w['code'] === 'property_use_mismatch'),
        );
    }

    #[Test]
    public function area_only_difference_is_partial_match(): void
    {
        $lead = new Lead([
            'address_line_1' => 'Calle Gloria 51',
            'city' => 'Santa Cruz de Mudela',
            'postcode' => '13730',
            'size_m2' => 200,
            'property_type' => 'detached',
        ]);

        $result = $this->service()->compare($lead, $this->property());

        $this->assertSame(CatastroVerificationStatus::PartiallyMatched, $result['status']);
        $this->assertTrue(
            collect($result['warnings'])->contains(fn (array $w) => $w['code'] === 'area_mismatch'),
        );
    }

    #[Test]
    public function does_not_warn_province_from_formatted_address(): void
    {
        $lead = new Lead([
            'address_line_1' => 'Calle Gloria 51',
            'city' => 'Santa Cruz de Mudela',
            'postcode' => '13730',
            'formatted_address' => 'Calle Gloria 51, 13730 Santa Cruz de Mudela, Valencia, Spain',
            'size_m2' => 308,
            'property_type' => 'detached',
        ]);

        $result = $this->service()->compare($lead, $this->property());

        $this->assertSame(CatastroVerificationStatus::Matched, $result['status']);
        $this->assertFalse(
            collect($result['warnings'])->contains(fn (array $w) => $w['code'] === 'province_mismatch'),
        );
    }
}
