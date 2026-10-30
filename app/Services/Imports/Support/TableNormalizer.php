<?php

namespace App\Services\Imports\Support;

use App\Enums\ColumnType;
use App\Enums\ImportFormat;
use App\Exceptions\ImportException;
use App\Services\Imports\Data\ColumnProfile;
use App\Services\Imports\Data\ParsedTable;
use DateTimeImmutable;

final class TableNormalizer
{
    /**
     * @param  array<int, array<int, string>>  $rawRows
     * @param  array<int, string>|null  $explicitHeaders
     */
    public function normalize(
        array $rawRows,
        ImportFormat $format,
        string $encoding,
        ?string $delimiter = null,
        ?string $sheetName = null,
        ?array $explicitHeaders = null,
    ): ParsedTable {
        $startedAt = microtime(true);

        if ($rawRows === []) {
            throw ImportException::emptyFile();
        }

        $maxRows = (int) config('datacanvas.limits.max_rows');
        $maxColumns = (int) config('datacanvas.limits.max_columns');
        $maxCells = (int) config('datacanvas.limits.max_cells');

        $columnCount = max(array_map('count', $rawRows));

        if ($columnCount > $maxColumns) {
            throw ImportException::tooManyColumns($maxColumns);
        }

        if (count($rawRows) > $maxRows + 1) {
            throw ImportException::tooManyRows($maxRows);
        }

        if ($explicitHeaders !== null) {
            $headers = $this->normalizeHeaders($explicitHeaders, $columnCount);
        } elseif ($this->looksLikeHeader($rawRows[0], $rawRows[1] ?? null)) {
            $headers = $this->normalizeHeaders(array_shift($rawRows), $columnCount);
        } else {
            $headers = $this->generatedHeaders($columnCount);
        }

        if ($rawRows === []) {
            throw ImportException::noDataRows();
        }

        if (count($rawRows) > $maxRows) {
            throw ImportException::tooManyRows($maxRows);
        }

        if (count($rawRows) * $columnCount > $maxCells) {
            throw ImportException::tooManyCells($maxCells);
        }

        $rows = [];
        $emptyCells = 0;
        $columnValues = array_fill(0, $columnCount, []);
        $columnEmptyCounts = array_fill(0, $columnCount, 0);

        foreach ($rawRows as $row) {
            $this->assertWithinTime($startedAt);

            $normalizedRow = [];

            for ($column = 0; $column < $columnCount; $column++) {
                $value = $this->normalizeValue((string) ($row[$column] ?? ''));
                $normalizedRow[] = $value;

                if ($value === '') {
                    $emptyCells++;
                    $columnEmptyCounts[$column]++;
                } else {
                    $columnValues[$column][] = $value;
                }
            }

            $rows[] = $normalizedRow;
        }

        $columns = [];

        foreach ($headers as $index => $name) {
            $columns[] = new ColumnProfile(
                name: $name,
                type: $this->inferColumnType($columnValues[$index]),
                emptyCount: $columnEmptyCounts[$index],
            );
        }

        return new ParsedTable(
            format: $format,
            columns: $columns,
            rows: $rows,
            rowCount: count($rows),
            columnCount: $columnCount,
            emptyCells: $emptyCells,
            encoding: $encoding,
            delimiter: $delimiter,
            sheetName: $sheetName,
        );
    }

    /**
     * @param  array<int, string>  $first
     * @param  array<int, string>|null  $second
     */
    private function looksLikeHeader(array $first, ?array $second): bool
    {
        if ($second === null || $first === []) {
            return false;
        }

        $nonEmpty = array_values(array_filter($first, fn (string $value): bool => trim($value) !== ''));

        if ($nonEmpty === [] || count($nonEmpty) < max(1, (int) ceil(count($first) * 0.6))) {
            return false;
        }

        $unique = array_map(fn (string $value): string => mb_strtolower(trim($value)), $nonEmpty);

        if (count(array_unique($unique)) !== count($unique)) {
            return false;
        }

        $allText = true;

        foreach ($first as $value) {
            if ($this->classifyValue((string) $value) !== ColumnType::Text) {
                $allText = false;

                break;
            }
        }

        if ($allText) {
            return true;
        }

        $typeDifferences = 0;
        $textHeaders = 0;

        foreach ($first as $index => $value) {
            $headerType = $this->classifyValue((string) $value);

            if ($headerType === ColumnType::Text) {
                $textHeaders++;
            }

            $dataType = $this->classifyValue((string) ($second[$index] ?? ''));

            if ($headerType !== ColumnType::Empty && $dataType !== ColumnType::Empty && $headerType !== $dataType) {
                $typeDifferences++;
            }
        }

        return $textHeaders > 0 && $typeDifferences > 0;
    }

    /**
     * @param  array<int, mixed>  $headers
     * @return array<int, string>
     */
    private function normalizeHeaders(array $headers, int $columnCount): array
    {
        $maxLength = (int) config('datacanvas.limits.max_header_length');
        $normalized = [];
        $seen = [];

        for ($index = 0; $index < $columnCount; $index++) {
            $name = trim((string) ($headers[$index] ?? ''));
            $name = (string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $name);
            $name = (string) preg_replace('/\s+/u', ' ', $name);
            $name = mb_substr($name, 0, $maxLength);

            if ($name === '') {
                $name = 'Coluna '.($index + 1);
            }

            $key = mb_strtolower($name);
            $seen[$key] = ($seen[$key] ?? 0) + 1;

            if ($seen[$key] > 1) {
                $suffix = ' ('.$seen[$key].')';
                $name = mb_substr($name, 0, max(1, $maxLength - strlen($suffix))).$suffix;
            }

            $normalized[] = $name;
        }

        return $normalized;
    }

    /**
     * @return array<int, string>
     */
    private function generatedHeaders(int $columnCount): array
    {
        $headers = [];

        for ($index = 0; $index < $columnCount; $index++) {
            $headers[] = 'Coluna '.($index + 1);
        }

        return $headers;
    }

    private function normalizeValue(string $value): string
    {
        $value = trim($value);

        return (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', '', $value);
    }

    /**
     * @param  array<int, string>  $values
     */
    private function inferColumnType(array $values): ColumnType
    {
        if ($values === []) {
            return ColumnType::Empty;
        }

        $types = [];

        foreach ($values as $value) {
            $types[$this->classifyValue($value)->value] = $this->classifyValue($value);
        }

        if (count($types) === 1) {
            return reset($types);
        }

        $keys = array_keys($types);

        if (array_diff($keys, [ColumnType::Integer->value, ColumnType::Decimal->value]) === []) {
            return ColumnType::Decimal;
        }

        if (array_diff($keys, [ColumnType::Date->value, ColumnType::DateTime->value]) === []) {
            return ColumnType::DateTime;
        }

        return ColumnType::Mixed;
    }

    private function classifyValue(string $value): ColumnType
    {
        $value = trim($value);

        if ($value === '') {
            return ColumnType::Empty;
        }

        if ($this->isBoolean($value)) {
            return ColumnType::Boolean;
        }

        if ($this->isDateTime($value)) {
            return ColumnType::DateTime;
        }

        if ($this->isDate($value)) {
            return ColumnType::Date;
        }

        return $this->numericType($value) ?? ColumnType::Text;
    }

    private function isBoolean(string $value): bool
    {
        return in_array(mb_strtolower($value), ['true', 'false', 'sim', 'não', 'nao', 'yes', 'no'], true);
    }

    private function isDate(string $value): bool
    {
        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d'] as $format) {
            if ($this->matchesDateFormat($value, $format)) {
                return true;
            }
        }

        return false;
    }

    private function isDateTime(string $value): bool
    {
        foreach (['d/m/Y H:i', 'd/m/Y H:i:s', 'd-m-Y H:i', 'Y-m-d H:i:s', 'Y-m-d\TH:i:sP'] as $format) {
            if ($this->matchesDateFormat($value, $format)) {
                return true;
            }
        }

        return false;
    }

    private function matchesDateFormat(string $value, string $format): bool
    {
        $date = DateTimeImmutable::createFromFormat('!'.$format, $value);
        $errors = DateTimeImmutable::getLastErrors();

        return $date !== false
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0));
    }

    private function numericType(string $value): ?ColumnType
    {
        $value = str_replace(["\u{00A0}", ' '], '', $value);

        if (str_starts_with($value, '(') && str_ends_with($value, ')')) {
            $value = '-'.trim($value, '()');
        }

        $value = (string) preg_replace('/^(R\$|US\$|\$|€|£)/iu', '', $value);
        $value = rtrim($value, '%');

        if ($value === '' || ! preg_match('/^-?[\d.,]+$/', $value)) {
            return null;
        }

        if (preg_match('/^-?\d+$/', $value)) {
            return ColumnType::Integer;
        }

        if (preg_match('/^-?\d{1,3}(?:[.,]\d{3})+(?:[.,]\d+)?$/', $value)
            || preg_match('/^-?\d+[.,]\d+$/', $value)) {
            return ColumnType::Decimal;
        }

        return null;
    }

    private function assertWithinTime(float $startedAt): void
    {
        $maxSeconds = (int) config('datacanvas.limits.max_processing_seconds');

        if ($maxSeconds > 0 && (microtime(true) - $startedAt) > $maxSeconds) {
            throw ImportException::processingTimeout();
        }
    }
}
