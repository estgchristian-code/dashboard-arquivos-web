<?php

namespace App\Services\Analysis;

class DataQualityService
{
    public function analyze(array $rows, array $columns): array
    {
        $totalCells = 0;
        $emptyCells = 0;
        $columnStats = [];
        foreach ($columns as $index => $column) {
            $empty = 0;
            $total = 0;
            $invalidEmails = 0;
            $invalidDates = 0;
            $invalidNumbers = 0;
            foreach ($rows as $row) {
                $total++;
                $value = trim((string) ($row[$index] ?? ''));
                if ($value === '') {
                    $empty++;
                    continue;
                }
                if (stripos($column['name'] ?? '', 'email') !== false || stripos($column['name'] ?? '', 'e-mail') !== false) {
                    if (! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $invalidEmails++;
                    }
                }
                if (stripos($column['name'] ?? '', 'data') !== false || stripos($column['name'] ?? '', 'date') !== false) {
                    if (strtotime($value) === false) {
                        $invalidDates++;
                    }
                }
                if (is_numeric($value) === false && preg_match('/^\d+([.,]\d+)?$/', $value)) {
                    $invalidNumbers++;
                }
            }
            $totalCells += $total;
            $emptyCells += $empty;
            $columnStats[] = [
                'name' => $column['name'] ?? ('Coluna ' . ($index + 1)),
                'empty' => $empty,
                'total' => $total,
                'completeness' => $total > 0 ? round((1 - $empty / $total) * 100, 1) : 100.0,
                'invalid_emails' => $invalidEmails,
                'invalid_dates' => $invalidDates,
                'invalid_numbers' => $invalidNumbers,
            ];
        }

        $duplicates = 0;
        $seen = [];
        foreach ($rows as $row) {
            $key = implode('|', $row);
            if (isset($seen[$key])) {
                $duplicates++;
            } else {
                $seen[$key] = true;
            }
        }

        return [
            'total_records' => count($rows),
            'total_columns' => count($columns),
            'empty_cells' => $emptyCells,
            'total_cells' => $totalCells,
            'completeness' => $totalCells > 0 ? round((1 - $emptyCells / $totalCells) * 100, 1) : 100.0,
            'duplicate_rows' => $duplicates,
            'columns' => $columnStats,
        ];
    }
}
