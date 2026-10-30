<?php

namespace App\Enums;

enum ImportFormat: string
{
    case Csv = 'csv';
    case Tsv = 'tsv';
    case Xlsx = 'xlsx';
    case Xls = 'xls';
    case Json = 'json';

    public function label(): string
    {
        return match ($this) {
            self::Csv => 'CSV',
            self::Tsv => 'TSV',
            self::Xlsx => 'XLSX',
            self::Xls => 'XLS',
            self::Json => 'JSON',
        };
    }

    public function extension(): string
    {
        return $this->value;
    }

    public function isText(): bool
    {
        return in_array($this, [self::Csv, self::Tsv, self::Json], true);
    }

    public static function fromExtension(string $extension): ?self
    {
        return match (strtolower($extension)) {
            'csv' => self::Csv,
            'tsv' => self::Tsv,
            'xlsx' => self::Xlsx,
            'xls' => self::Xls,
            'json' => self::Json,
            default => null,
        };
    }

    /**
     * Extensões aceitas para o formato detectado.
     *
     * @return array<int, string>
     */
    public function allowedExtensions(): array
    {
        return match ($this) {
            self::Csv => ['csv', 'tsv'],
            self::Tsv => ['csv', 'tsv'],
            default => [$this->extension()],
        };
    }
}
