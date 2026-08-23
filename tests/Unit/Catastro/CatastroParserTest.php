<?php

namespace Tests\Unit\Catastro;

use App\Enums\CatastroProvider;
use App\Enums\CatastroVerificationStatus;
use App\Services\Catastro\CatastroReferenceNormalizer;
use App\Services\Catastro\CatastroResponseParser;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CatastroParserTest extends TestCase
{
    #[Test]
    public function it_normalises_valid_references(): void
    {
        $this->assertSame(
            '9872023VH5797S0001WX',
            CatastroReferenceNormalizer::normalize('9872023 VH5797S 0001 WX'),
        );
    }

    #[Test]
    public function it_rejects_invalid_references(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CatastroReferenceNormalizer::normalize('BAD');
    }

    #[Test]
    public function it_parses_single_dnprc_result(): void
    {
        $payload = json_decode(
            file_get_contents(base_path('tests/Fixtures/catastro_dnprc_single.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $result = app(CatastroResponseParser::class)->parseDnprc($payload);

        $this->assertSame(CatastroProvider::National, $result->provider);
        $this->assertCount(1, $result->properties);
        $this->assertSame('9872023VH5797S0001WX', $result->properties[0]->cadastralReference);
        $this->assertSame(308.0, $result->properties[0]->constructedAreaM2);
        $this->assertSame('Residencial', $result->properties[0]->propertyUse);
    }

    #[Test]
    public function it_parses_official_dnprc_xml_response(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/catastro_dnprc_single.xml'));

        $result = app(CatastroResponseParser::class)->parseXml($xml);

        $this->assertSame(CatastroVerificationStatus::ManualReviewRequired, $result->status);
        $this->assertSame(CatastroProvider::National, $result->provider);
        $this->assertCount(1, $result->properties);
        $this->assertSame('9872023VH5797S0001WX', $result->properties[0]->cadastralReference);
        $this->assertSame('CIUDAD REAL', $result->properties[0]->province);
        $this->assertSame(308.0, $result->properties[0]->constructedAreaM2);
        $this->assertSame(397.0, $result->properties[0]->parcelAreaM2);
        $this->assertSame(1980, $result->properties[0]->constructionYear);
    }

    #[Test]
    public function it_contains_malformed_xml_errors(): void
    {
        $result = app(CatastroResponseParser::class)->parseXml('<consulta_dnp><broken>');

        $this->assertSame(CatastroVerificationStatus::LookupFailed, $result->status);
        $this->assertSame('Malformed Catastro response.', $result->message);
    }

    #[Test]
    public function it_rejects_xml_with_external_entities(): void
    {
        $xml = <<<'XML'
        <?xml version="1.0"?>
        <!DOCTYPE consulta_dnp [
          <!ENTITY secret SYSTEM "file:///etc/passwd">
        ]>
        <consulta_dnp><control><cudnp>&secret;</cudnp></control></consulta_dnp>
        XML;

        $result = app(CatastroResponseParser::class)->parseXml($xml);

        $this->assertSame(CatastroVerificationStatus::LookupFailed, $result->status);
        $this->assertSame('Malformed Catastro response.', $result->message);
    }

    #[Test]
    public function it_marks_property_not_found(): void
    {
        $result = app(CatastroResponseParser::class)->parseDnprc([
            'consulta_dnprcResult' => [
                'lerr' => [
                    'cod' => '43',
                    'des' => 'No existe el inmueble',
                ],
            ],
        ]);

        $this->assertSame(CatastroVerificationStatus::PropertyNotFound, $result->status);
    }
}
