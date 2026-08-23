<?php

namespace App\Services\Demo;

use App\Enums\CompanyType;
use App\Models\Company;
use App\Models\Lead;
use App\Support\SpanishLocations;
use Illuminate\Support\Collection;

/**
 * Applies reliable Spanish coordinates to demo leads/companies without calling Google.
 */
class DemoLocationBackfillService
{
    /** @var list<string> */
    private const CITIES = [
        'Madrid',
        'Barcelona',
        'Valencia',
        'Sevilla',
        'Málaga',
        'Alicante',
        'Murcia',
        'Granada',
        'Zaragoza',
        'Bilbao',
    ];

    /**
     * @return array{leads_updated: int, companies_updated: int, leads_already_ok: int, companies_already_ok: int}
     */
    public function backfill(bool $onlyMissing = true): array
    {
        $leadsUpdated = 0;
        $leadsOk = 0;
        $companiesUpdated = 0;
        $companiesOk = 0;

        foreach ($this->demoLeads() as $index => $lead) {
            if ($onlyMissing && $lead->latitude !== null && $lead->longitude !== null) {
                $leadsOk++;

                continue;
            }

            $city = $this->resolveCity($lead->city, (string) $lead->lead_reference, $index);
            $variant = $this->variantFromReference((string) $lead->lead_reference);
            $lead->forceFill(SpanishLocations::leadAttributes($city, $variant))->save();
            $leadsUpdated++;
        }

        foreach ($this->demoCompanies() as $index => $company) {
            if ($onlyMissing && $company->latitude !== null && $company->longitude !== null) {
                $companiesOk++;

                continue;
            }

            $city = $this->resolveCity($company->city, (string) $company->name, $index);
            $company->forceFill(SpanishLocations::companyAttributes($city))->save();
            $companiesUpdated++;
        }

        return [
            'leads_updated' => $leadsUpdated,
            'companies_updated' => $companiesUpdated,
            'leads_already_ok' => $leadsOk,
            'companies_already_ok' => $companiesOk,
        ];
    }

    /**
     * @return Collection<int, Lead>
     */
    private function demoLeads(): Collection
    {
        return Lead::query()
            ->where(function ($q) {
                $q->where('lead_reference', 'like', 'LD-%')
                    ->orWhere('customer_email', 'like', '%@example.es');
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, Company>
     */
    private function demoCompanies(): Collection
    {
        return Company::query()
            ->whereIn('type', [CompanyType::Buyer->value, CompanyType::Seller->value])
            ->where(function ($q) {
                $q->where('email', 'like', '%@demo.rml.test')
                    ->orWhere('notes', 'like', 'Demo %');
            })
            ->orderBy('id')
            ->get();
    }

    private function resolveCity(?string $city, string $seed, int $index): string
    {
        if (is_string($city) && trim($city) !== '' && SpanishLocations::hasCity($city)) {
            return SpanishLocations::forCity($city)['city'];
        }

        return self::CITIES[abs(crc32($seed.'|'.$index)) % count(self::CITIES)];
    }

    private function variantFromReference(string $reference): int
    {
        $digits = preg_replace('/\D/', '', $reference) ?: '0';

        return (int) substr($digits, -2);
    }
}
