<?php

namespace App\Services\Analysis;

use App\Enums\ChartType;
use App\Enums\ColumnType;
use App\Services\Analysis\Data\ChartRecommendation;
use App\Services\Analysis\Data\ColumnAnalysis;
use App\Services\Analysis\Data\ImportAnalysis;
use App\Services\Imports\Data\StoredImport;
use App\Services\Imports\ImportRepository;
use DateTimeImmutable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class DataAnalyzer
{
    public function __construct(
        private readonly ImportRepository $importRepository,
    ) {}

    public function analyze(string $importId, string $ownerHash): ImportAnalysis
    {
        $import = $this->importRepository->findOwned($importId, $ownerHash);
        if ($import === null) {
            throw new \InvalidArgumentException('Importação não encontrada.');
        }

        $fullRows = $this->loadFullRows($importId);
        $totalRecords = count($fullRows);
        if ($totalRecords === 0 && count($import->previewRows) > 0) {
            $fullRows = $import->previewRows;
            $totalRecords = count($fullRows);
        }

        $columnAnalyses = $this->analyzeColumns($import, $fullRows);
        $recommendations = $this->recommendCharts($import, $fullRows, $columnAnalyses);

        $issues = $this->detectIssues($columnAnalyses, $recommendations, $totalRecords);
        $summary = [
            'total_records' => $totalRecords,
            'total_columns' => count($import->columns),
            'recommendations_count' => count($recommendations),
        ];

        $analysis = new ImportAnalysis(
            importId: $importId,
            totalRecords: $totalRecords,
            columnAnalyses: $columnAnalyses,
            recommendations: $recommendations,
            summary: $summary,
            issues: $issues,
        );

        $this->storeAnalysis($importId, $analysis);

        return $analysis;
    }

    public function getStoredAnalysis(string $importId, string $ownerHash): ?ImportAnalysis
    {
        $import = $this->importRepository->findOwned($importId, $ownerHash);
        if ($import === null) {
            return null;
        }
        $disk = $this->disk();
        if (! $disk->exists($importId.'/analysis.json')) {
            return null;
        }
        try {
            $contents = $disk->get($importId.'/analysis.json');
            $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($data)) {
                return null;
            }
            $columnAnalyses = [];
            foreach (($data['column_analyses'] ?? []) as $ca) {
                $columnAnalyses[] = new ColumnAnalysis(
                    name: (string) ($ca['name'] ?? ''),
                    type: (string) ($ca['type'] ?? 'text'),
                    isIdentifier: (bool) ($ca['is_identifier'] ?? false),
                    totalCount: (int) ($ca['total_count'] ?? 0),
                    nonEmptyCount: (int) ($ca['non_empty_count'] ?? 0),
                    emptyCount: (int) ($ca['empty_count'] ?? 0),
                    distinctCount: (int) ($ca['distinct_count'] ?? 0),
                    duplicateCount: (int) ($ca['duplicate_count'] ?? 0),
                    min: $ca['min'] ?? null,
                    max: $ca['max'] ?? null,
                    sum: $ca['sum'] ?? null,
                    mean: $ca['mean'] ?? null,
                    median: $ca['median'] ?? null,
                    categories: is_array($ca['categories'] ?? null) ? $ca['categories'] : [],
                    hasTemporalTrend: (bool) ($ca['has_temporal_trend'] ?? false),
                );
            }
            $recommendations = [];
            foreach (($data['recommendations'] ?? []) as $rec) {
                $recommendations[] = new ChartRecommendation(
                    chartType: (string) ($rec['chart_type'] ?? ChartType::Bar->value),
                    title: (string) ($rec['title'] ?? ''),
                    justification: (string) ($rec['justification'] ?? ''),
                    metrics: is_array($rec['metrics'] ?? null) ? $rec['metrics'] : [],
                    aggregatedData: is_array($rec['aggregated_data'] ?? null) ? $rec['aggregated_data'] : [],
                    columns: is_array($rec['columns'] ?? null) ? $rec['columns'] : [],
                    issues: is_array($rec['issues'] ?? null) ? $rec['issues'] : [],
                );
            }

            return new ImportAnalysis(
                importId: (string) ($data['import_id'] ?? $importId),
                totalRecords: (int) ($data['total_records'] ?? 0),
                columnAnalyses: $columnAnalyses,
                recommendations: $recommendations,
                summary: is_array($data['summary'] ?? null) ? $data['summary'] : [],
                issues: is_array($data['issues'] ?? null) ? $data['issues'] : [],
            );
        } catch (Throwable) {
            return null;
        }
    }

    private function loadFullRows(string $importId): array
    {
        $disk = $this->disk();
        try {
            if (! $disk->exists($importId.'/data.json')) {
                return [];
            }
            $contents = $disk->get($importId.'/data.json');
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($decoded)) {
                return [];
            }
            $rows = $decoded['rows'] ?? [];
            if (! is_array($rows)) {
                return [];
            }
            $normalized = [];
            foreach (array_values($rows) as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $normalized[] = array_map(
                    fn (mixed $v): string => is_scalar($v) ? (string) $v : '',
                    array_values($row),
                );
            }

            return $normalized;
        } catch (Throwable) {
            return [];
        }
    }

    private function analyzeColumns(StoredImport $import, array $rows): array
    {
        $analyses = [];
        $columnCount = $import->columnCount;
        $totalRecords = count($rows);

        foreach ($import->columns as $index => $colMeta) {
            $name = $colMeta['name'] ?? ('Coluna '.($index + 1));
            $type = $colMeta['type'] ?? ColumnType::Mixed->value;

            $values = [];
            $emptyCount = 0;
            foreach ($rows as $row) {
                $v = $row[$index] ?? '';
                if ($v === '') {
                    $emptyCount++;

                    continue;
                }
                $values[] = $v;
            }

            $nonEmptyCount = count($values);
            $distinctValues = array_values(array_unique($values, SORT_REGULAR));
            $distinctCount = count($distinctValues);
            $duplicateCount = max(0, $nonEmptyCount - $distinctCount);

            $isIdentifier = false;
            if ($nonEmptyCount > 0 && $totalRecords > 1) {
                $lower = mb_strtolower($name);
                // Very strict - only if explicitly looks like id AND all unique
                if (preg_match('/^id$|_id$|codigo$|cod$|chave$|uuid$/', $lower) || preg_match('/\bid\b|\bcodigo\b|\bcod\b/', $lower)) {
                    if ($distinctCount === $nonEmptyCount && $nonEmptyCount >= 2) {
                        $isIdentifier = true;
                    }
                }
            }

            $min = null;
            $max = null;
            $sum = null;
            $mean = null;
            $median = null;
            $categories = [];

            if (! $isIdentifier && in_array($type, [ColumnType::Integer->value, ColumnType::Decimal->value], true)) {
                $nums = [];
                foreach ($values as $v) {
                    $n = $this->toNumber($v);
                    if ($n !== null) {
                        $nums[] = $n;
                    }
                }
                if (! empty($nums)) {
                    $sorted = $nums;
                    sort($sorted, SORT_NUMERIC);
                    $min = $sorted[0];
                    $max = $sorted[count($sorted) - 1];
                    $sum = array_sum($nums);
                    $mean = $sum / count($nums);
                    $count = count($sorted);
                    if ($count % 2 === 1) {
                        $median = $sorted[(int) floor($count / 2)];
                    } else {
                        $median = ($sorted[intdiv($count, 2) - 1] + $sorted[intdiv($count, 2)]) / 2;
                    }
                }
            } elseif (in_array($type, [ColumnType::Text->value, ColumnType::Boolean->value], true) && ! $isIdentifier) {
                $counts = [];
                foreach ($values as $v) {
                    $key = mb_strtolower(trim($v));
                    if ($key === '') {
                        continue;
                    }
                    $counts[$key] = ($counts[$key] ?? 0) + 1;
                }
                arsort($counts);
                $categories = [];
                $i = 0;
                foreach ($counts as $k => $c) {
                    if ($i >= 20) {
                        break;
                    }
                    $categories[] = ['value' => $k, 'count' => $c];
                    $i++;
                }
            }

            $hasTemporalTrend = false;
            if (in_array($type, [ColumnType::Date->value, ColumnType::DateTime->value], true)) {
                $hasTemporalTrend = $nonEmptyCount >= 2;
            }

            $analyses[] = new ColumnAnalysis(
                name: $name,
                type: $type,
                isIdentifier: $isIdentifier,
                totalCount: $totalRecords,
                nonEmptyCount: $nonEmptyCount,
                emptyCount: $emptyCount,
                distinctCount: $distinctCount,
                duplicateCount: $duplicateCount,
                min: $min,
                max: $max,
                sum: $sum,
                mean: is_float($mean) ? round($mean, 6) : $mean,
                median: is_float($median) ? round($median, 6) : $median,
                categories: $categories,
                hasTemporalTrend: $hasTemporalTrend,
            );
        }

        return $analyses;
    }

    private function toNumber(string $value): float|int|null
    {
        $v = str_replace(["\u{00A0}", ' '], '', $value);
        if (str_starts_with($v, '(') && str_ends_with($v, ')')) {
            $v = '-'.trim($v, '()');
        }
        $v = (string) preg_replace('/^(R\$|US\$|\$|€|£)/iu', '', $v);
        $v = rtrim($v, '%');
        if ($v === '' || ! preg_match('/^-?[\d.,]+$/', $v)) {
            return null;
        }
        if (preg_match('/^-?\d{1,3}(?:\.\d{3})+(?:,\d+)?$/', $v)) {
            $v = str_replace('.', '', $v);
            $v = str_replace(',', '.', $v);
        } elseif (preg_match('/^-?\d+,\d+$/', $v)) {
            $v = str_replace(',', '.', $v);
        }
        if (str_contains($v, '.')) {
            return (float) $v;
        }
        if (preg_match('/^-?\d+$/', $v)) {
            return (int) $v;
        }

        return (float) $v;
    }

    private function recommendCharts(StoredImport $import, array $rows, array $columnAnalyses): array
    {
        $recs = [];
        $totalRecords = count($rows);

        $numericCols = [];
        $catCols = [];
        $dateCols = [];
        foreach ($columnAnalyses as $idx => $ca) {
            if ($ca->isIdentifier) {
                continue;
            }
            if (in_array($ca->type, [ColumnType::Integer->value, ColumnType::Decimal->value], true) && $ca->nonEmptyCount > 0) {
                $numericCols[] = ['idx' => $idx, 'analysis' => $ca];
            } elseif (in_array($ca->type, [ColumnType::Text->value, ColumnType::Boolean->value], true) && $ca->nonEmptyCount > 0 && $ca->distinctCount >= 1) {
                $catCols[] = ['idx' => $idx, 'analysis' => $ca];
            } elseif (in_array($ca->type, [ColumnType::Date->value, ColumnType::DateTime->value], true)) {
                $dateCols[] = ['idx' => $idx, 'analysis' => $ca];
            }
        }

        foreach ($numericCols as $nc) {
            $ca = $nc['analysis'];
            if ($ca->nonEmptyCount >= 3 && $ca->distinctCount >= 2) {
                $values = $this->getColumnValues($rows, $nc['idx']);
                $nums = [];
                foreach ($values as $v) {
                    $n = $this->toNumber($v);
                    if ($n !== null) {
                        $nums[] = $n;
                    }
                }
                if (count($nums) >= 3) {
                    sort($nums, SORT_NUMERIC);
                    $bins = $this->makeHistogramBins($nums, 5);
                    $recs[] = new ChartRecommendation(
                        chartType: ChartType::Histogram->value,
                        title: 'Distribuição de '.$ca->name,
                        justification: 'Distribuição de valores numéricos adequada para histograma.',
                        metrics: ['count' => count($nums), 'min' => $ca->min, 'max' => $ca->max, 'mean' => $ca->mean],
                        aggregatedData: $bins,
                        columns: [$ca->name],
                        issues: [],
                    );
                }
            }
        }

        foreach ($catCols as $cc) {
            $ca = $cc['analysis'];
            if ($ca->distinctCount >= 2 && $ca->distinctCount <= 20 && $ca->nonEmptyCount >= 2) {
                $aggregated = $ca->categories;
                if ($ca->distinctCount <= 6) {
                    $recs[] = new ChartRecommendation(
                        chartType: ChartType::Pie->value,
                        title: 'Proporção por '.$ca->name,
                        justification: 'Categorias com baixa cardinalidade permitem visualização em pizza.',
                        metrics: ['distinct' => $ca->distinctCount, 'total' => $ca->nonEmptyCount],
                        aggregatedData: $aggregated,
                        columns: [$ca->name],
                        issues: [],
                    );
                } else {
                    $recs[] = new ChartRecommendation(
                        chartType: ChartType::Bar->value,
                        title: 'Distribuição por '.$ca->name,
                        justification: 'Categorias adequadas para gráfico de barras.',
                        metrics: ['distinct' => $ca->distinctCount, 'total' => $ca->nonEmptyCount],
                        aggregatedData: $aggregated,
                        columns: [$ca->name],
                        issues: [],
                    );
                }
            }
        }

        if (! empty($catCols) && ! empty($numericCols) && $totalRecords >= 2) {
            foreach ($catCols as $cc) {
                if ($cc['analysis']->distinctCount < 2 || $cc['analysis']->distinctCount > 15) {
                    continue;
                }
                foreach ($numericCols as $nc) {
                    $agg = $this->aggregateByCategory($rows, $cc['idx'], $nc['idx']);
                    if (count($agg) >= 2) {
                        $recs[] = new ChartRecommendation(
                            chartType: ChartType::Bar->value,
                            title: 'Soma de '.$nc['analysis']->name.' por '.$cc['analysis']->name,
                            justification: 'Combinação categoria + medida numérica permite agregação por barras.',
                            metrics: ['groups' => count($agg)],
                            aggregatedData: $agg,
                            columns: [$cc['analysis']->name, $nc['analysis']->name],
                            issues: [],
                        );
                        break 2;
                    }
                }
            }
        }

        if (! empty($dateCols) && ! empty($numericCols) && $totalRecords >= 2) {
            $dc = $dateCols[0];
            $nc = $numericCols[0];
            $series = $this->buildTimeSeries($rows, $dc['idx'], $nc['idx']);
            if (count($series) >= 2) {
                $recs[] = new ChartRecommendation(
                    chartType: ChartType::Line->value,
                    title: $nc['analysis']->name.' ao longo do tempo',
                    justification: 'Dados temporais com medida numérica permitem série temporal.',
                    metrics: ['points' => count($series)],
                    aggregatedData: $series,
                    columns: [$dc['analysis']->name, $nc['analysis']->name],
                    issues: [],
                );
                $recs[] = new ChartRecommendation(
                    chartType: ChartType::Area->value,
                    title: $nc['analysis']->name.' ao longo do tempo (área)',
                    justification: 'Série temporal adequada para gráfico de área.',
                    metrics: ['points' => count($series)],
                    aggregatedData: $series,
                    columns: [$dc['analysis']->name, $nc['analysis']->name],
                    issues: [],
                );
            }
        }

        if (count($numericCols) >= 2 && $totalRecords >= 3) {
            $nc1 = $numericCols[0];
            $nc2 = $numericCols[1];
            if (! $nc1['analysis']->isIdentifier && ! $nc2['analysis']->isIdentifier) {
                $points = $this->buildScatter($rows, $nc1['idx'], $nc2['idx']);
                if (count($points) >= 3) {
                    $recs[] = new ChartRecommendation(
                        chartType: ChartType::Scatter->value,
                        title: $nc2['analysis']->name.' x '.$nc1['analysis']->name,
                        justification: 'Duas variáveis numéricas permitem análise de correlação/dispersão.',
                        metrics: ['points' => count($points)],
                        aggregatedData: $points,
                        columns: [$nc1['analysis']->name, $nc2['analysis']->name],
                        issues: [],
                    );
                }
            }
        }

        $seen = [];
        $unique = [];
        foreach ($recs as $r) {
            $key = $r->chartType.'|'.implode(',', $r->columns);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $r;
        }

        return $unique;
    }

    private function getColumnValues(array $rows, int $idx): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[] = $row[$idx] ?? '';
        }

        return $out;
    }

    private function makeHistogramBins(array $nums, int $binCount): array
    {
        if (empty($nums)) {
            return [];
        }
        $min = $nums[0];
        $max = $nums[count($nums) - 1];
        if ($min == $max) {
            return [['bin' => (string) $min, 'count' => count($nums), 'range' => [$min, $max]]];
        }
        $width = ($max - $min) / $binCount;
        $bins = [];
        for ($i = 0; $i < $binCount; $i++) {
            $bmin = $min + $i * $width;
            $bmax = $i == $binCount - 1 ? $max : ($min + ($i + 1) * $width);
            $count = 0;
            foreach ($nums as $n) {
                if ($i == $binCount - 1) {
                    if ($n >= $bmin && $n <= $bmax) {
                        $count++;
                    }
                } else {
                    if ($n >= $bmin && $n < $bmax) {
                        $count++;
                    }
                }
            }
            $bins[] = ['bin' => round($bmin, 3).' - '.round($bmax, 3), 'count' => $count, 'range' => [round($bmin, 6), round($bmax, 6)]];
        }

        return $bins;
    }

    private function aggregateByCategory(array $rows, int $catIdx, int $numIdx): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $cat = trim($row[$catIdx] ?? '');
            if ($cat === '') {
                continue;
            }
            $n = $this->toNumber($row[$numIdx] ?? '');
            if ($n === null) {
                continue;
            }
            $key = mb_strtolower($cat);
            if (! isset($groups[$key])) {
                $groups[$key] = ['label' => $cat, 'sum' => 0, 'count' => 0];
            }
            $groups[$key]['sum'] += is_numeric($n) ? $n : 0;
            $groups[$key]['count']++;
        }
        $out = [];
        foreach ($groups as $g) {
            $out[] = ['category' => $g['label'], 'value' => round($g['sum'], 6), 'count' => $g['count']];
        }
        usort($out, fn ($a, $b) => $b['value'] <=> $a['value']);

        return array_slice($out, 0, 15);
    }

    private function buildTimeSeries(array $rows, int $dateIdx, int $numIdx): array
    {
        $points = [];
        foreach ($rows as $row) {
            $dstr = trim($row[$dateIdx] ?? '');
            if ($dstr === '') {
                continue;
            }
            $n = $this->toNumber($row[$numIdx] ?? '');
            if ($n === null) {
                continue;
            }
            $dt = $this->parseDate($dstr);
            if ($dt === null) {
                continue;
            }
            $points[] = ['date' => $dt->format('Y-m-d'), 'timestamp' => $dt->getTimestamp(), 'value' => is_numeric($n) ? (is_float($n) ? round($n, 6) : $n) : 0];
        }
        usort($points, fn ($a, $b) => $a['timestamp'] <=> $b['timestamp']);
        $grouped = [];
        foreach ($points as $p) {
            $k = $p['date'];
            if (! isset($grouped[$k])) {
                $grouped[$k] = ['date' => $k, 'value' => 0, 'count' => 0];
            }
            $grouped[$k]['value'] += is_numeric($p['value']) ? $p['value'] : 0;
            $grouped[$k]['count']++;
        }
        $out = [];
        foreach ($grouped as $g) {
            $out[] = ['date' => $g['date'], 'value' => round($g['value'], 6)];
        }
        usort($out, fn ($a, $b) => strcmp($a['date'], $b['date']));

        return $out;
    }

    private function parseDate(string $value): ?DateTimeImmutable
    {
        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'd/m/Y H:i', 'd/m/Y H:i:s', 'Y-m-d H:i:s'] as $fmt) {
            $d = DateTimeImmutable::createFromFormat('!'.$fmt, $value);
            $errors = DateTimeImmutable::getLastErrors();
            if ($d !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $d->setTime(0, 0);
            }
        }
        try {
            $d = new DateTimeImmutable($value);

            return $d->setTime(0, 0);
        } catch (Throwable) {
            return null;
        }
    }

    private function buildScatter(array $rows, int $xIdx, int $yIdx): array
    {
        $points = [];
        foreach ($rows as $row) {
            $x = $this->toNumber($row[$xIdx] ?? '');
            $y = $this->toNumber($row[$yIdx] ?? '');
            if ($x === null || $y === null) {
                continue;
            }
            $points[] = ['x' => is_float($x) ? round($x, 6) : $x, 'y' => is_float($y) ? round($y, 6) : $y];
        }

        return array_slice($points, 0, 1000);
    }

    private function detectIssues(array $columnAnalyses, array $recommendations, int $totalRecords): array
    {
        $issues = ['insufficient_data' => [], 'inconsistent' => []];
        if ($totalRecords < 2) {
            $issues['insufficient_data'][] = 'Poucos registros para gerar recomendações confiáveis.';
        }
        foreach ($columnAnalyses as $ca) {
            if ($ca->emptyCount > 0 && $ca->totalCount > 0 && $ca->emptyCount > ($ca->totalCount * 0.5)) {
                $issues['inconsistent'][] = 'Coluna "'.$ca->name.'" possui muitos valores vazios.';
            }
            if ($ca->isIdentifier && $ca->duplicateCount > 0) {
                $issues['inconsistent'][] = 'Identificador "'.$ca->name.'" apresenta duplicidades.';
            }
        }
        if (empty($recommendations)) {
            $issues['insufficient_data'][] = 'Não há combinações suficientes para recomendar gráficos com segurança.';
        }

        return $issues;
    }

    private function storeAnalysis(string $importId, ImportAnalysis $analysis): void
    {
        $disk = $this->disk();
        try {
            $data = json_encode(
                $analysis->toArray(),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
            $disk->put($importId.'/analysis.json', $data);
        } catch (Throwable) {
        }
    }

    private function disk(): Filesystem
    {
        return Storage::disk((string) config('datacanvas.disk', 'imports'));
    }
}
