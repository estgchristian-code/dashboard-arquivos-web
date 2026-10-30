<?php

namespace App\Services\Imports\Data;

use App\Enums\ImportFormat;

final readonly class ParsedTable
{
    /**
     * @param  array<int, ColumnProfile>  $columns
     * @param  array<int, array<int, string>>  $rows
     */
    public function __construct(
        public ImportFormat $format,
        public array $columns,
        public array $rows,
        public int $rowCount,
        public int $columnCount,
        public int $emptyCells,
        public string $encoding,
        public ?string $delimiter = null,
        public ?string $sheetName = null,
    ) {}
}
