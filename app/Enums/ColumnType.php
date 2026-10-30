<?php

namespace App\Enums;

enum ColumnType: string
{
    case Text = 'text';
    case Integer = 'integer';
    case Decimal = 'decimal';
    case Date = 'date';
    case DateTime = 'datetime';
    case Boolean = 'boolean';
    case Empty = 'empty';
    case Mixed = 'mixed';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Texto',
            self::Integer => 'Inteiro',
            self::Decimal => 'Decimal',
            self::Date => 'Data',
            self::DateTime => 'Data/hora',
            self::Boolean => 'Booleano',
            self::Empty => 'Vazio',
            self::Mixed => 'Misto',
        };
    }
}
