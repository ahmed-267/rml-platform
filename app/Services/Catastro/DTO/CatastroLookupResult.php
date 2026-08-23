<?php

namespace App\Services\Catastro\DTO;

use App\Enums\CatastroProvider;
use App\Enums\CatastroVerificationStatus;

final class CatastroLookupResult
{
    /**
     * @param  list<CatastroProperty>  $properties
     * @param  array<string, mixed>|null  $raw
     */
    public function __construct(
        public readonly CatastroVerificationStatus $status,
        public readonly CatastroProvider $provider,
        public readonly array $properties = [],
        public readonly ?string $message = null,
        public readonly ?string $providerRequestId = null,
        public readonly ?array $raw = null,
    ) {}

    public function isSuccess(): bool
    {
        return in_array($this->status, [
            CatastroVerificationStatus::Matched,
            CatastroVerificationStatus::PartiallyMatched,
            CatastroVerificationStatus::MultiplePropertiesFound,
            CatastroVerificationStatus::ManualReviewRequired,
        ], true) || ($this->status === CatastroVerificationStatus::NotChecked && $this->properties !== []);
    }

    public function singleProperty(): ?CatastroProperty
    {
        return count($this->properties) === 1 ? $this->properties[0] : null;
    }
}
