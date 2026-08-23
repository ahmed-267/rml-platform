<?php

namespace App\Support;

use App\Enums\GeocodingStatus;

/**
 * Realistic Spanish demo locations for maps / distance foundations.
 *
 * @phpstan-type LocationArray array{
 *     address_line_1: string,
 *     address?: string,
 *     city: string,
 *     postcode: string,
 *     country: string,
 *     latitude: float,
 *     longitude: float,
 *     formatted_address: string
 * }
 */
final class SpanishLocations
{
    /**
     * @return array<string, LocationArray>
     */
    public static function catalog(): array
    {
        return [
            'Madrid' => [
                'address_line_1' => 'Calle Gran Vía 28',
                'address' => 'Calle Gran Vía 28',
                'city' => 'Madrid',
                'postcode' => '28013',
                'country' => 'ES',
                'latitude' => 40.4203140,
                'longitude' => -3.7057740,
                'formatted_address' => 'Calle de Gran Vía, 28, 28013 Madrid, Spain',
            ],
            'Valencia' => [
                'address_line_1' => 'Calle Colón 36',
                'address' => 'Calle Colón 36',
                'city' => 'Valencia',
                'postcode' => '46004',
                'country' => 'ES',
                'latitude' => 39.4699075,
                'longitude' => -0.3762881,
                'formatted_address' => 'Carrer de Colón, 36, 46004 València, Spain',
            ],
            'Sevilla' => [
                'address_line_1' => 'Avenida de la Constitución 1',
                'address' => 'Avenida de la Constitución 1',
                'city' => 'Sevilla',
                'postcode' => '41001',
                'country' => 'ES',
                'latitude' => 37.3861380,
                'longitude' => -5.9926110,
                'formatted_address' => 'Av. de la Constitución, 1, 41001 Sevilla, Spain',
            ],
            'Málaga' => [
                'address_line_1' => 'Calle Larios 5',
                'address' => 'Calle Larios 5',
                'city' => 'Málaga',
                'postcode' => '29015',
                'country' => 'ES',
                'latitude' => 36.7201600,
                'longitude' => -4.4203400,
                'formatted_address' => 'Calle Marqués de Larios, 5, 29015 Málaga, Spain',
            ],
            'Alicante' => [
                'address_line_1' => 'Rambla Méndez Núñez 41',
                'address' => 'Rambla Méndez Núñez 41',
                'city' => 'Alicante',
                'postcode' => '03002',
                'country' => 'ES',
                'latitude' => 38.3451700,
                'longitude' => -0.4810060,
                'formatted_address' => 'Rambla Méndez Núñez, 41, 03002 Alicante, Spain',
            ],
            'Murcia' => [
                'address_line_1' => 'Gran Vía Alfonso X el Sabio 5',
                'address' => 'Gran Vía Alfonso X el Sabio 5',
                'city' => 'Murcia',
                'postcode' => '30008',
                'country' => 'ES',
                'latitude' => 37.9861130,
                'longitude' => -1.1300400,
                'formatted_address' => 'Gran Vía Alfonso X el Sabio, 5, 30008 Murcia, Spain',
            ],
            'Granada' => [
                'address_line_1' => 'Calle Reyes Católicos 55',
                'address' => 'Calle Reyes Católicos 55',
                'city' => 'Granada',
                'postcode' => '18001',
                'country' => 'ES',
                'latitude' => 37.1760870,
                'longitude' => -3.5976470,
                'formatted_address' => 'Calle Reyes Católicos, 55, 18001 Granada, Spain',
            ],
            'Zaragoza' => [
                'address_line_1' => 'Paseo de la Independencia 24',
                'address' => 'Paseo de la Independencia 24',
                'city' => 'Zaragoza',
                'postcode' => '50004',
                'country' => 'ES',
                'latitude' => 41.6488230,
                'longitude' => -0.8890850,
                'formatted_address' => 'P.º de la Independencia, 24, 50004 Zaragoza, Spain',
            ],
            'Barcelona' => [
                'address_line_1' => 'Passeig de Gràcia 92',
                'address' => 'Passeig de Gràcia 92',
                'city' => 'Barcelona',
                'postcode' => '08008',
                'country' => 'ES',
                'latitude' => 41.3954000,
                'longitude' => 2.1611200,
                'formatted_address' => 'Passeig de Gràcia, 92, 08008 Barcelona, Spain',
            ],
            'Bilbao' => [
                'address_line_1' => 'Gran Vía Don Diego López de Haro 45',
                'address' => 'Gran Vía Don Diego López de Haro 45',
                'city' => 'Bilbao',
                'postcode' => '48011',
                'country' => 'ES',
                'latitude' => 43.2630000,
                'longitude' => -2.9349900,
                'formatted_address' => 'Gran Vía Don Diego López de Haro, 45, 48011 Bilbao, Spain',
            ],
            'Córdoba' => [
                'address_line_1' => 'Calle Cruz Conde 18',
                'address' => 'Calle Cruz Conde 18',
                'city' => 'Córdoba',
                'postcode' => '14003',
                'country' => 'ES',
                'latitude' => 37.8882000,
                'longitude' => -4.7794000,
                'formatted_address' => 'Calle Cruz Conde, 18, 14003 Córdoba, Spain',
            ],
            // Nearby Madrid metros used by existing demo leads
            'Alcalá de Henares' => [
                'address_line_1' => 'Calle Mayor 15',
                'address' => 'Calle Mayor 15',
                'city' => 'Alcalá de Henares',
                'postcode' => '28801',
                'country' => 'ES',
                'latitude' => 40.4818000,
                'longitude' => -3.3639000,
                'formatted_address' => 'Calle Mayor, 15, 28801 Alcalá de Henares, Madrid, Spain',
            ],
            'Getafe' => [
                'address_line_1' => 'Calle Madrid 42',
                'address' => 'Calle Madrid 42',
                'city' => 'Getafe',
                'postcode' => '28901',
                'country' => 'ES',
                'latitude' => 40.3082000,
                'longitude' => -3.7327000,
                'formatted_address' => 'Calle Madrid, 42, 28901 Getafe, Madrid, Spain',
            ],
            'Leganés' => [
                'address_line_1' => 'Avenida de Gibraltar 8',
                'address' => 'Avenida de Gibraltar 8',
                'city' => 'Leganés',
                'postcode' => '28912',
                'country' => 'ES',
                'latitude' => 40.3272000,
                'longitude' => -3.7635000,
                'formatted_address' => 'Av. de Gibraltar, 8, 28912 Leganés, Madrid, Spain',
            ],
        ];
    }

    /**
     * @return LocationArray
     */
    public static function forCity(string $city): array
    {
        $catalog = self::catalog();
        $normalized = self::normalize($city);

        foreach ($catalog as $name => $location) {
            if (self::normalize($name) === $normalized) {
                return $location;
            }
        }

        return $catalog['Madrid'];
    }

    public static function hasCity(string $city): bool
    {
        $normalized = self::normalize($city);

        foreach (array_keys(self::catalog()) as $name) {
            if (self::normalize($name) === $normalized) {
                return true;
            }
        }

        // Also recognise residential variant cities (same keys as catalog + metros).
        foreach (array_keys(self::residentialVariants()) as $name) {
            if (self::normalize($name) === $normalized) {
                return true;
            }
        }

        return false;
    }

    /**
     * Seed payload for a lead property at a Spanish city.
     * Pass $variant to pick a distinct street in the same city (avoids stacked pins).
     *
     * @return array<string, mixed>
     */
    public static function leadAttributes(string $city, int $variant = 0): array
    {
        $location = self::residentialForCity($city, $variant);

        return [
            'address_line_1' => $location['address_line_1'],
            'city' => $location['city'],
            'postcode' => $location['postcode'],
            'country' => $location['country'],
            'latitude' => $location['latitude'],
            'longitude' => $location['longitude'],
            'formatted_address' => $location['formatted_address'],
            'geocoding_status' => GeocodingStatus::Successful,
            'geocoded_at' => now(),
            'geocoding_error' => null,
            'cadastral_lookup_status' => null,
        ];
    }

    /**
     * @return LocationArray
     */
    public static function residentialForCity(string $city, int $variant = 0): array
    {
        $variants = self::residentialVariants();
        $normalized = self::normalize($city);

        foreach ($variants as $name => $list) {
            if (self::normalize($name) === $normalized && $list !== []) {
                $index = abs($variant) % count($list);

                return $list[$index];
            }
        }

        return self::forCity($city);
    }

    /**
     * Multiple realistic residential addresses per city for demo leads.
     *
     * @return array<string, list<LocationArray>>
     */
    private static function residentialVariants(): array
    {
        return [
            'Madrid' => [
                [
                    'address_line_1' => 'Calle de Serrano 45',
                    'address' => 'Calle de Serrano 45',
                    'city' => 'Madrid',
                    'postcode' => '28001',
                    'country' => 'ES',
                    'latitude' => 40.4278000,
                    'longitude' => -3.6879000,
                    'formatted_address' => 'Calle de Serrano, 45, 28001 Madrid, Spain',
                ],
                [
                    'address_line_1' => 'Calle de Bravo Murillo 122',
                    'address' => 'Calle de Bravo Murillo 122',
                    'city' => 'Madrid',
                    'postcode' => '28020',
                    'country' => 'ES',
                    'latitude' => 40.4486000,
                    'longitude' => -3.7034000,
                    'formatted_address' => 'Calle de Bravo Murillo, 122, 28020 Madrid, Spain',
                ],
                [
                    'address_line_1' => 'Calle de Alcalá 210',
                    'address' => 'Calle de Alcalá 210',
                    'city' => 'Madrid',
                    'postcode' => '28028',
                    'country' => 'ES',
                    'latitude' => 40.4292000,
                    'longitude' => -3.6698000,
                    'formatted_address' => 'Calle de Alcalá, 210, 28028 Madrid, Spain',
                ],
                [
                    'address_line_1' => 'Paseo de la Castellana 89',
                    'address' => 'Paseo de la Castellana 89',
                    'city' => 'Madrid',
                    'postcode' => '28046',
                    'country' => 'ES',
                    'latitude' => 40.4471000,
                    'longitude' => -3.6912000,
                    'formatted_address' => 'P.º de la Castellana, 89, 28046 Madrid, Spain',
                ],
            ],
            'Barcelona' => [
                [
                    'address_line_1' => 'Carrer de Provença 233',
                    'address' => 'Carrer de Provença 233',
                    'city' => 'Barcelona',
                    'postcode' => '08008',
                    'country' => 'ES',
                    'latitude' => 41.3938000,
                    'longitude' => 2.1594000,
                    'formatted_address' => 'Carrer de Provença, 233, 08008 Barcelona, Spain',
                ],
                [
                    'address_line_1' => 'Carrer de València 312',
                    'address' => 'Carrer de València 312',
                    'city' => 'Barcelona',
                    'postcode' => '08009',
                    'country' => 'ES',
                    'latitude' => 41.3972000,
                    'longitude' => 2.1701000,
                    'formatted_address' => 'Carrer de València, 312, 08009 Barcelona, Spain',
                ],
                [
                    'address_line_1' => 'Avinguda Diagonal 440',
                    'address' => 'Avinguda Diagonal 440',
                    'city' => 'Barcelona',
                    'postcode' => '08037',
                    'country' => 'ES',
                    'latitude' => 41.3989000,
                    'longitude' => 2.1556000,
                    'formatted_address' => 'Av. Diagonal, 440, 08037 Barcelona, Spain',
                ],
            ],
            'Valencia' => [
                [
                    'address_line_1' => 'Calle de Cirilo Amorós 68',
                    'address' => 'Calle de Cirilo Amorós 68',
                    'city' => 'Valencia',
                    'postcode' => '46004',
                    'country' => 'ES',
                    'latitude' => 39.4682000,
                    'longitude' => -0.3709000,
                    'formatted_address' => 'Carrer de Ciril Amorós, 68, 46004 València, Spain',
                ],
                [
                    'address_line_1' => 'Avenida del Reino de Valencia 46',
                    'address' => 'Avenida del Reino de Valencia 46',
                    'city' => 'Valencia',
                    'postcode' => '46005',
                    'country' => 'ES',
                    'latitude' => 39.4608000,
                    'longitude' => -0.3664000,
                    'formatted_address' => 'Av. del Regne de València, 46, 46005 València, Spain',
                ],
                [
                    'address_line_1' => 'Calle de Pérez Galdós 15',
                    'address' => 'Calle de Pérez Galdós 15',
                    'city' => 'Valencia',
                    'postcode' => '46018',
                    'country' => 'ES',
                    'latitude' => 39.4726000,
                    'longitude' => -0.3891000,
                    'formatted_address' => 'Carrer de Pérez Galdós, 15, 46018 València, Spain',
                ],
            ],
            'Sevilla' => [
                [
                    'address_line_1' => 'Calle San Fernando 12',
                    'address' => 'Calle San Fernando 12',
                    'city' => 'Sevilla',
                    'postcode' => '41004',
                    'country' => 'ES',
                    'latitude' => 37.3829000,
                    'longitude' => -5.9908000,
                    'formatted_address' => 'Calle San Fernando, 12, 41004 Sevilla, Spain',
                ],
                [
                    'address_line_1' => 'Avenida de la República Argentina 24',
                    'address' => 'Avenida de la República Argentina 24',
                    'city' => 'Sevilla',
                    'postcode' => '41011',
                    'country' => 'ES',
                    'latitude' => 37.3756000,
                    'longitude' => -5.9982000,
                    'formatted_address' => 'Av. de la República Argentina, 24, 41011 Sevilla, Spain',
                ],
                [
                    'address_line_1' => 'Calle Asunción 33',
                    'address' => 'Calle Asunción 33',
                    'city' => 'Sevilla',
                    'postcode' => '41011',
                    'country' => 'ES',
                    'latitude' => 37.3778000,
                    'longitude' => -6.0021000,
                    'formatted_address' => 'Calle Asunción, 33, 41011 Sevilla, Spain',
                ],
            ],
            'Málaga' => [
                [
                    'address_line_1' => 'Calle Strachan 8',
                    'address' => 'Calle Strachan 8',
                    'city' => 'Málaga',
                    'postcode' => '29015',
                    'country' => 'ES',
                    'latitude' => 36.7218000,
                    'longitude' => -4.4219000,
                    'formatted_address' => 'Calle Strachan, 8, 29015 Málaga, Spain',
                ],
                [
                    'address_line_1' => 'Avenida de Andalucía 15',
                    'address' => 'Avenida de Andalucía 15',
                    'city' => 'Málaga',
                    'postcode' => '29007',
                    'country' => 'ES',
                    'latitude' => 36.7146000,
                    'longitude' => -4.4352000,
                    'formatted_address' => 'Av. de Andalucía, 15, 29007 Málaga, Spain',
                ],
                [
                    'address_line_1' => 'Calle Compás de la Victoria 6',
                    'address' => 'Calle Compás de la Victoria 6',
                    'city' => 'Málaga',
                    'postcode' => '29012',
                    'country' => 'ES',
                    'latitude' => 36.7264000,
                    'longitude' => -4.4168000,
                    'formatted_address' => 'Calle Compás de la Victoria, 6, 29012 Málaga, Spain',
                ],
            ],
            'Alicante' => [
                [
                    'address_line_1' => 'Avenida de Maisonnave 30',
                    'address' => 'Avenida de Maisonnave 30',
                    'city' => 'Alicante',
                    'postcode' => '03003',
                    'country' => 'ES',
                    'latitude' => 38.3436000,
                    'longitude' => -0.4908000,
                    'formatted_address' => 'Av. de Maisonnave, 30, 03003 Alicante, Spain',
                ],
                [
                    'address_line_1' => 'Calle San Francisco 18',
                    'address' => 'Calle San Francisco 18',
                    'city' => 'Alicante',
                    'postcode' => '03001',
                    'country' => 'ES',
                    'latitude' => 38.3469000,
                    'longitude' => -0.4832000,
                    'formatted_address' => 'Calle San Francisco, 18, 03001 Alicante, Spain',
                ],
            ],
            'Murcia' => [
                [
                    'address_line_1' => 'Avenida de la Constitución 8',
                    'address' => 'Avenida de la Constitución 8',
                    'city' => 'Murcia',
                    'postcode' => '30008',
                    'country' => 'ES',
                    'latitude' => 37.9894000,
                    'longitude' => -1.1286000,
                    'formatted_address' => 'Av. de la Constitución, 8, 30008 Murcia, Spain',
                ],
                [
                    'address_line_1' => 'Calle Trapería 22',
                    'address' => 'Calle Trapería 22',
                    'city' => 'Murcia',
                    'postcode' => '30001',
                    'country' => 'ES',
                    'latitude' => 37.9838000,
                    'longitude' => -1.1299000,
                    'formatted_address' => 'Calle Trapería, 22, 30001 Murcia, Spain',
                ],
            ],
            'Granada' => [
                [
                    'address_line_1' => 'Calle Recogidas 44',
                    'address' => 'Calle Recogidas 44',
                    'city' => 'Granada',
                    'postcode' => '18005',
                    'country' => 'ES',
                    'latitude' => 37.1728000,
                    'longitude' => -3.6014000,
                    'formatted_address' => 'Calle Recogidas, 44, 18005 Granada, Spain',
                ],
                [
                    'address_line_1' => 'Gran Vía de Colón 29',
                    'address' => 'Gran Vía de Colón 29',
                    'city' => 'Granada',
                    'postcode' => '18001',
                    'country' => 'ES',
                    'latitude' => 37.1769000,
                    'longitude' => -3.5988000,
                    'formatted_address' => 'Gran Vía de Colón, 29, 18001 Granada, Spain',
                ],
            ],
            'Zaragoza' => [
                [
                    'address_line_1' => 'Calle Alfonso I 18',
                    'address' => 'Calle Alfonso I 18',
                    'city' => 'Zaragoza',
                    'postcode' => '50003',
                    'country' => 'ES',
                    'latitude' => 41.6549000,
                    'longitude' => -0.8786000,
                    'formatted_address' => 'Calle Alfonso I, 18, 50003 Zaragoza, Spain',
                ],
                [
                    'address_line_1' => 'Paseo de Sagasta 52',
                    'address' => 'Paseo de Sagasta 52',
                    'city' => 'Zaragoza',
                    'postcode' => '50006',
                    'country' => 'ES',
                    'latitude' => 41.6408000,
                    'longitude' => -0.8902000,
                    'formatted_address' => 'P.º de Sagasta, 52, 50006 Zaragoza, Spain',
                ],
            ],
            'Bilbao' => [
                [
                    'address_line_1' => 'Calle Ercilla 24',
                    'address' => 'Calle Ercilla 24',
                    'city' => 'Bilbao',
                    'postcode' => '48009',
                    'country' => 'ES',
                    'latitude' => 43.2612000,
                    'longitude' => -2.9338000,
                    'formatted_address' => 'Calle Ercilla, 24, 48009 Bilbao, Spain',
                ],
                [
                    'address_line_1' => 'Alameda de Recalde 17',
                    'address' => 'Alameda de Recalde 17',
                    'city' => 'Bilbao',
                    'postcode' => '48009',
                    'country' => 'ES',
                    'latitude' => 43.2628000,
                    'longitude' => -2.9364000,
                    'formatted_address' => 'Alameda de Recalde, 17, 48009 Bilbao, Spain',
                ],
            ],
            'Getafe' => [
                [
                    'address_line_1' => 'Calle Madrid 42',
                    'address' => 'Calle Madrid 42',
                    'city' => 'Getafe',
                    'postcode' => '28901',
                    'country' => 'ES',
                    'latitude' => 40.3082000,
                    'longitude' => -3.7327000,
                    'formatted_address' => 'Calle Madrid, 42, 28901 Getafe, Madrid, Spain',
                ],
            ],
            'Leganés' => [
                [
                    'address_line_1' => 'Avenida de Gibraltar 8',
                    'address' => 'Avenida de Gibraltar 8',
                    'city' => 'Leganés',
                    'postcode' => '28912',
                    'country' => 'ES',
                    'latitude' => 40.3272000,
                    'longitude' => -3.7635000,
                    'formatted_address' => 'Av. de Gibraltar, 8, 28912 Leganés, Madrid, Spain',
                ],
            ],
            'Alcalá de Henares' => [
                [
                    'address_line_1' => 'Calle Mayor 15',
                    'address' => 'Calle Mayor 15',
                    'city' => 'Alcalá de Henares',
                    'postcode' => '28801',
                    'country' => 'ES',
                    'latitude' => 40.4818000,
                    'longitude' => -3.3639000,
                    'formatted_address' => 'Calle Mayor, 15, 28801 Alcalá de Henares, Madrid, Spain',
                ],
            ],
        ];
    }

    /**
     * Seed payload for a company base / warehouse location.
     * Uses a nearby industrial/commercial street when available so pins do not stack on residential leads.
     *
     * @return array<string, mixed>
     */
    public static function companyAttributes(string $city): array
    {
        $warehouses = self::warehouseCatalog();
        $normalized = self::normalize($city);
        $location = null;

        foreach ($warehouses as $name => $warehouse) {
            if (self::normalize($name) === $normalized) {
                $location = $warehouse;
                break;
            }
        }

        $location ??= self::forCity($city);

        return [
            'address' => $location['address'],
            'city' => $location['city'],
            'postcode' => $location['postcode'],
            'country' => $location['country'],
            'latitude' => $location['latitude'],
            'longitude' => $location['longitude'],
            'formatted_address' => $location['formatted_address'],
            'geocoding_status' => GeocodingStatus::Successful,
            'geocoded_at' => now(),
            'geocoding_error' => null,
        ];
    }

    /**
     * @return array<string, LocationArray>
     */
    private static function warehouseCatalog(): array
    {
        return [
            'Madrid' => [
                'address_line_1' => 'Calle de Méndez Álvaro 56',
                'address' => 'Calle de Méndez Álvaro 56',
                'city' => 'Madrid',
                'postcode' => '28045',
                'country' => 'ES',
                'latitude' => 40.3959000,
                'longitude' => -3.6784000,
                'formatted_address' => 'Calle de Méndez Álvaro, 56, 28045 Madrid, Spain',
            ],
            'Valencia' => [
                'address_line_1' => 'Avenida del Puerto 120',
                'address' => 'Avenida del Puerto 120',
                'city' => 'Valencia',
                'postcode' => '46022',
                'country' => 'ES',
                'latitude' => 39.4558000,
                'longitude' => -0.3371000,
                'formatted_address' => 'Av. del Puerto, 120, 46022 València, Spain',
            ],
            'Sevilla' => [
                'address_line_1' => 'Calle Torneo 48',
                'address' => 'Calle Torneo 48',
                'city' => 'Sevilla',
                'postcode' => '41002',
                'country' => 'ES',
                'latitude' => 37.3965000,
                'longitude' => -6.0028000,
                'formatted_address' => 'Calle Torneo, 48, 41002 Sevilla, Spain',
            ],
            'Málaga' => [
                'address_line_1' => 'Avenida de Velázquez 180',
                'address' => 'Avenida de Velázquez 180',
                'city' => 'Málaga',
                'postcode' => '29004',
                'country' => 'ES',
                'latitude' => 36.6952000,
                'longitude' => -4.4548000,
                'formatted_address' => 'Av. de Velázquez, 180, 29004 Málaga, Spain',
            ],
            'Alicante' => [
                'address_line_1' => 'Avenida de Elche 110',
                'address' => 'Avenida de Elche 110',
                'city' => 'Alicante',
                'postcode' => '03008',
                'country' => 'ES',
                'latitude' => 38.3325000,
                'longitude' => -0.5089000,
                'formatted_address' => 'Av. de Elche, 110, 03008 Alicante, Spain',
            ],
            'Barcelona' => [
                'address_line_1' => 'Carrer de Badal 137',
                'address' => 'Carrer de Badal 137',
                'city' => 'Barcelona',
                'postcode' => '08014',
                'country' => 'ES',
                'latitude' => 41.3751000,
                'longitude' => 2.1358000,
                'formatted_address' => 'Carrer de Badal, 137, 08014 Barcelona, Spain',
            ],
            'Bilbao' => [
                'address_line_1' => 'Calle Autonomía 52',
                'address' => 'Calle Autonomía 52',
                'city' => 'Bilbao',
                'postcode' => '48012',
                'country' => 'ES',
                'latitude' => 43.2558000,
                'longitude' => -2.9401000,
                'formatted_address' => 'Calle Autonomía, 52, 48012 Bilbao, Spain',
            ],
            'Zaragoza' => [
                'address_line_1' => 'Avenida de Cataluña 80',
                'address' => 'Avenida de Cataluña 80',
                'city' => 'Zaragoza',
                'postcode' => '50014',
                'country' => 'ES',
                'latitude' => 41.6662000,
                'longitude' => -0.8584000,
                'formatted_address' => 'Av. de Cataluña, 80, 50014 Zaragoza, Spain',
            ],
            'Granada' => [
                'address_line_1' => 'Camino de Ronda 124',
                'address' => 'Camino de Ronda 124',
                'city' => 'Granada',
                'postcode' => '18003',
                'country' => 'ES',
                'latitude' => 37.1709000,
                'longitude' => -3.6098000,
                'formatted_address' => 'Camino de Ronda, 124, 18003 Granada, Spain',
            ],
            'Murcia' => [
                'address_line_1' => 'Avenida de la Libertad 6',
                'address' => 'Avenida de la Libertad 6',
                'city' => 'Murcia',
                'postcode' => '30009',
                'country' => 'ES',
                'latitude' => 37.9928000,
                'longitude' => -1.1432000,
                'formatted_address' => 'Av. de la Libertad, 6, 30009 Murcia, Spain',
            ],
        ];
    }

    private static function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $map = [
            'á' => 'a', 'à' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u',
            'ñ' => 'n',
        ];

        return strtr($value, $map);
    }
}
