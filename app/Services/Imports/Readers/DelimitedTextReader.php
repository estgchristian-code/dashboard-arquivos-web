<?php

namespace App\Services\Imports\Readers;

use App\Enums\ImportFormat;
use App\Exceptions\ImportException;
use App\Services\Imports\Data\ParsedTable;
use App\Services\Imports\Support\TableNormalizer;
use App\Services\Imports\Support\TextFile;

final class DelimitedTextReader implements TabularReader
{
    public function __construct(private readonly TableNormalizer $normalizer) {}

    public function read(string $path, ImportFormat $format): ParsedTable
    {
        $content = @file_get_contents($path);

        if ($content === false) {
            throw ImportException::corruptSpreadsheet();
        }

        $decoded = TextFile::decode($content);
        $delimiter = TextFile::detectDelimiter($decoded['text']);
        $actualFormat = $delimiter === "\t" ? ImportFormat::Tsv : ImportFormat::Csv;

        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw ImportException::corruptSpreadsheet();
        }

        try {
            fwrite($stream, $decoded['text']);
            rewind($stream);

            $rows = [];
            $maxRows = (int) config('datacanvas.limits.max_rows');
            $maxColumns = (int) config('datacanvas.limits.max_columns');
            $maxCells = (int) config('datacanvas.limits.max_cells');
            $maxColumnsFound = 0;
            $maxSeconds = (int) config('datacanvas.limits.max_processing_seconds');
            $startedAt = microtime(true);

            while (($row = fgetcsv($stream, 0, $delimiter, '"', '')) !== false) {
                if ($maxSeconds > 0 && (microtime(true) - $startedAt) > $maxSeconds) {
                    throw ImportException::processingTimeout();
                }

                $row = array_map(
                    fn (mixed $value): string => $value === null ? '' : (string) $value,
                    $row,
                );

                if ($this->isEmptyRow($row)) {
                    continue;
                }

                $maxColumnsFound = max($maxColumnsFound, count($row));

                if ($maxColumnsFound > $maxColumns) {
                    throw ImportException::tooManyColumns($maxColumns);
                }

                $rows[] = $row;

                if (count($rows) > $maxRows + 1) {
                    throw ImportException::tooManyRows($maxRows);
                }

                if (count($rows) * $maxColumnsFound > $maxCells) {
                    throw ImportException::tooManyCells($maxCells);
                }
            }

            if ($rows === []) {
                throw ImportException::emptyFile();
            }

            return $this->normalizer->normalize(
                rawRows: $rows,
                format: $actualFormat,
                encoding: $decoded['encoding'],
                delimiter: $delimiter,
            );
        } finally {
            fclose($stream);
        }
    }

    /**
     * @param  array<int, string>  $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim($value) !== '') {
                return false;
            }
        }

        return true;
    }
}
