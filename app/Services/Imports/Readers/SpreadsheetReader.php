<?php

namespace App\Services\Imports\Readers;

use App\Enums\ImportFormat;
use App\Exceptions\ImportException;
use App\Services\Imports\Data\ParsedTable;
use App\Services\Imports\Support\TableNormalizer;
use App\Services\Imports\Support\ZipGuard;
use DateTimeInterface;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

final class SpreadsheetReader implements TabularReader
{
    public function __construct(
        private readonly TableNormalizer $normalizer,
        private readonly ZipGuard $zipGuard,
    ) {}

    public function read(string $path, ImportFormat $format): ParsedTable
    {
        if ($format === ImportFormat::Xlsx) {
            $this->zipGuard->inspect($path);
        }

        try {
            $type = IOFactory::identify($path);
            $expectedType = $format === ImportFormat::Xlsx ? 'Xlsx' : 'Xls';

            if (strcasecmp($type, $expectedType) !== 0) {
                throw ImportException::formatMismatch();
            }

            $reader = IOFactory::createReader($type);
            $reader->setReadDataOnly(true);

            if (method_exists($reader, 'setReadEmptyCells')) {
                $reader->setReadEmptyCells(true);
            }

            if (method_exists($reader, 'setIncludeCharts')) {
                $reader->setIncludeCharts(false);
            }

            $spreadsheet = $reader->load($path);
            $sheet = $spreadsheet->getSheet(0);
            $sheetName = $sheet->getTitle();
            $highestRow = $sheet->getHighestDataRow();
            $highestColumn = $sheet->getHighestDataColumn();
            $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

            $maxRows = (int) config('datacanvas.limits.max_rows');
            $maxColumns = (int) config('datacanvas.limits.max_columns');
            $maxCells = (int) config('datacanvas.limits.max_cells');
            $maxSeconds = (int) config('datacanvas.limits.max_processing_seconds');

            if ($highestRow > $maxRows + 1) {
                throw ImportException::tooManyRows($maxRows);
            }

            if ($highestColumnIndex > $maxColumns) {
                throw ImportException::tooManyColumns($maxColumns);
            }

            if ($highestRow * $highestColumnIndex > $maxCells) {
                throw ImportException::tooManyCells($maxCells);
            }

            $rows = [];
            $startedAt = microtime(true);

            for ($rowIndex = 1; $rowIndex <= $highestRow; $rowIndex++) {
                if ($maxSeconds > 0 && (microtime(true) - $startedAt) > $maxSeconds) {
                    throw ImportException::processingTimeout();
                }

                $row = [];

                for ($columnIndex = 1; $columnIndex <= $highestColumnIndex; $columnIndex++) {
                    $row[] = $this->cellToString($sheet->getCell([$columnIndex, $rowIndex])->getValue());
                }

                if (! $this->isEmptyRow($row)) {
                    $rows[] = $row;
                }
            }

            $spreadsheet->disconnectWorksheets();

            if ($rows === []) {
                throw ImportException::emptyFile();
            }

            return $this->normalizer->normalize(
                rawRows: $rows,
                format: $format,
                encoding: 'UTF-8',
                sheetName: $sheetName,
            );
        } catch (ImportException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw ImportException::corruptSpreadsheet();
        }
    }

    private function cellToString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_object($value) && method_exists($value, 'getPlainText')) {
            return (string) $value->getPlainText();
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        return '';
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
