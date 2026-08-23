<?php

namespace App\Enums;

enum SurveyMeasurementMethod: string
{
    case ManualTape = 'manual_tape';
    case Laser = 'laser';
    case PropertyPlan = 'property_plan';
    case EpcCertificate = 'epc_certificate';
    case CadastralOnly = 'cadastral_reference_only';
    case VisualEstimate = 'visual_estimate';
    case Other = 'other';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
