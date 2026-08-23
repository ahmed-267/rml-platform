<?php

namespace Tests\Unit;

use App\Enums\GeocodingStatus;
use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\Scheme;
use App\Models\User;
use App\Services\Demo\DemoLocationBackfillService;
use App\Support\SpanishLocations;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SchemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoLocationBackfillTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class,
            DemoUserSeeder::class,
            SchemeSeeder::class,
        ]);
    }

    public function test_backfill_sets_coordinates_on_demo_leads_without_calling_google(): void
    {
        $user = User::query()->where('email', 'seller.admin@rml.test')->firstOrFail();
        $scheme = Scheme::query()->where('slug', 'insulation')->firstOrFail();

        $lead = Lead::query()->create([
            'lead_reference' => 'LD-9999',
            'submitted_by_user_id' => $user->id,
            'scheme_id' => $scheme->id,
            'status' => LeadStatus::Listed,
            'customer_first_name' => 'Demo',
            'customer_last_name' => 'Lead',
            'customer_phone' => '+34600999999',
            'customer_email' => 'demo.lead.ld-9999@example.es',
            'address_line_1' => '222 main st',
            'city' => 'mADRID',
            'postcode' => '00000',
            'country' => 'ES',
            'latitude' => null,
            'longitude' => null,
            'geocoding_status' => null,
        ]);

        $result = (new DemoLocationBackfillService)->backfill(onlyMissing: true);

        $lead->refresh();

        $this->assertSame(1, $result['leads_updated']);
        $this->assertNotNull($lead->latitude);
        $this->assertNotNull($lead->longitude);
        $this->assertSame(GeocodingStatus::Successful, $lead->geocoding_status);
        $this->assertNotEmpty($lead->formatted_address);
        $this->assertTrue(SpanishLocations::hasCity((string) $lead->city));
    }

    public function test_lead_attributes_variants_are_distinct(): void
    {
        $a = SpanishLocations::leadAttributes('Madrid', 0);
        $b = SpanishLocations::leadAttributes('Madrid', 1);

        $this->assertNotSame($a['address_line_1'], $b['address_line_1']);
        $this->assertNotEquals($a['latitude'], $b['latitude']);
    }
}
