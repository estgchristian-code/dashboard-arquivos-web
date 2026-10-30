<?php

namespace App\Enums;

enum ChartType: string
{
    case Bar = 'bar';
    case Line = 'line';
    case Area = 'area';
    case Pie = 'pie';
    case Scatter = 'scatter';
    case Histogram = 'histogram';

    public function label(): string
    {
        return match ($this) {
            self::Bar => 'Barras',
            self::Line => 'Linhas',
            self::Area => 'Área',
            self::Pie => 'Pizza',
            self::Scatter => 'Dispersão',
            self::Histogram => 'Histograma',
        };
    }
}
