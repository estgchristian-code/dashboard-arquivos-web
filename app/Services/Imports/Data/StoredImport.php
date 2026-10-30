<?php

namespace App\Services\Imports\Data;

use App\Enums\ImportFormat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;

final readonly class StoredImport
{
    /**
     * @param  array<int, array{name: string, type: string, type_label: string, empty_count: int}>  $columns
     * @param  array<int, array<int, string>>  $previewRows
     */
    public function __construct(
        public string $id,
        public string $originalName,
        public ImportFormat $format,
        public int $sizeBytes,
        public int $rowCount,
        public int $columnCount,
        public int $emptyCells,
        public string $encoding,
        public ?string $delimiter,
        public ?string $sheetName,
        public array $columns,
        public array $previewRows,
        public Carbon $createdAt,
        public Carbon $expiresAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toViewArray(): array
    {
        return [
            'id' => $this->id,
            'original_name' => $this->originalName,
            'format' => $this->format->value,
            'format_label' => $this->format->label(),
            'size_bytes' => $this->sizeBytes,
            'size_label' => Number::fileSize($this->sizeBytes, 1),
            'row_count' => $this->rowCount,
            'column_count' => $this->columnCount,
            'empty_cells' => $this->emptyCells,
            'encoding' => $this->encoding,
            'delimiter' => $this->delimiter,
            'delimiter_label' => self::delimiterLabel($this->delimiter),
            'sheet_name' => $this->sheetName,
            'columns' => $this->columns,
            'rows' => $this->previewRows,
            'preview_row_count' => count($this->previewRows),
            'has_more' => $this->rowCount > count($this->previewRows),
            'created_at' => $this->createdAt->format('d/m/Y H:i'),
            'expires_at' => $this->expiresAt->format('d/m/Y H:i'),
        ];
    }

    public static function delimiterLabel(?string $delimiter): ?string
    {
        return match ($delimiter) {
            ',' => 'Vírgula',
            ';' => 'Ponto e vírgula',
            "\t" => 'Tabulação',
            '|' => 'Pipe',
            default => null,
        };
    }
}
