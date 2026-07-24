<?php

namespace App\Enums;

enum SchemeFieldType: string
{
    case Text = 'text';
    case Number = 'number';
    case Select = 'select';
    case Textarea = 'textarea';
    case Checkbox = 'checkbox';
    case File = 'file';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
