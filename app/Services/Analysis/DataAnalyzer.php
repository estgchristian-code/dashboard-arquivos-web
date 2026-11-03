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
    private const ANALYSIS_VERSION = 2;

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
        if (count($fullRows) === 0 && count($import->previewRows) > 0) {
            $fullRows = $import->previewRows;
        }

        $analysis = $this->analyzeRows($import, $fullRows, $importId);

        $this->storeAnalysis($importId, $analysis);

        return $analysis;
    }

    /**
     * Analisa um subconjunto de linhas já filtrado, sem gravar cache.
     *
     * @param  array<int, array<int, string>>  $rows
     */
    public function analyzeRows(StoredImport $import, array $rows, ?string $importId = null): ImportAnalysis
    {
        $normalized = [];
        foreach (array_values($rows) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $normalized[] = array_map(
                fn (mixed $value): string => is_scalar($value) ? (string) $value : '',
                array_values($row),
            );
        }

        $columnAnalyses = $this->analyzeColumns($import, $normalized);
        $recommendations = $this->recommendCharts($import, $normalized, $columnAnalyses);
        $totalRecords = count($normalized);
        $summary = $this->buildSummary($import, $columnAnalyses, $recommendations, $totalRecords);

        return new ImportAnalysis(
            importId: $importId ?? $import->id,
            totalRecords: $totalRecords,
            columnAnalyses: $columnAnalyses,
            recommendations: $recommendations,
            summary: $summary,
            issues: $this->detectIssues($columnAnalyses, $recommendations, $totalRecords),
        );
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
            if ((int) ($data['analysis_version'] ?? 0) !== self::ANALYSIS_VERSION) {
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
                    isEmail: (bool) ($ca['is_email'] ?? false),
                    isName: (bool) ($ca['is_name'] ?? false),
                    emailDomains: is_array($ca['email_domains'] ?? null) ? $ca['email_domains'] : [],
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
            $isEmail = false;
            $isName = false;
            $emailDomains = [];

            if (! $isIdentifier && in_array($type, [ColumnType::Text->value, ColumnType::Mixed->value], true) && $nonEmptyCount > 0) {
                $emailCount = 0;
                $domainCounts = [];
                foreach ($values as $value) {
                    $candidate = trim($value);
                    if (! filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                        continue;
                    }
                    $emailCount++;
                    $at = strrpos($candidate, '@');
                    $domain = $at === false ? '' : mb_strtolower(substr($candidate, $at + 1));
                    if ($domain !== '') {
                        $domainCounts[$domain] = ($domainCounts[$domain] ?? 0) + 1;
                    }
                }

                if ($emailCount >= max(1, (int) ceil($nonEmptyCount * 0.8))) {
                    $isEmail = true;
                    arsort($domainCounts);
                    $i = 0;
                    foreach ($domainCounts as $domain => $count) {
                        if ($i >= 20) {
                            break;
                        }
                        $emailDomains[] = ['value' => $domain, 'count' => $count];
                        $i++;
                    }
                    $categories = $emailDomains;
                }

                if (! $isEmail && $nonEmptyCount >= 2 && $distinctCount >= 2) {
                    $lowerName = mb_strtolower($name);
                    $headerNameHint = preg_match('/\b(nome|name|cliente|contato|respons[aá]vel|pessoa|usu[aá]rio|colaborador|funcion[aá]rio|aluno|participante|s[oó]cio)\b/u', $lowerName) === 1;
                    $isName = $headerNameHint;
                }
            }

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
            } elseif (! $isEmail && in_array($type, [ColumnType::Text->value, ColumnType::Boolean->value], true) && ! $isIdentifier) {
                $counts = [];
                $labels = [];
                foreach ($values as $v) {
                    $label = trim($v);
                    $key = mb_strtolower($label);
                    if ($key === '') {
                        continue;
                    }
                    // Agrupa sem diferenciar maiúsculas, mas preserva a grafia original.
                    $labels[$key] ??= $label;
                    $counts[$key] = ($counts[$key] ?? 0) + 1;
                }
                arsort($counts);
                $categories = [];
                $i = 0;
                foreach ($counts as $k => $c) {
                    if ($i >= 20) {
                        break;
                    }
                    $categories[] = ['value' => $labels[$k] ?? $k, 'count' => $c];
                    $i++;
                }
            }

            if (! $isIdentifier && in_array($type, [ColumnType::Date->value, ColumnType::DateTime->value], true) && $nonEmptyCount > 0) {
                $dates = [];
                foreach ($values as $value) {
                    $parsed = $this->parseDate(trim($value));
                    if ($parsed !== null) {
                        $dates[] = $parsed->format('Y-m-d');
                    }
                }
                if (! empty($dates)) {
                    sort($dates);
                    $min = $dates[0];
                    $max = $dates[count($dates) - 1];
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
                isEmail: $isEmail,
                isName: $isName,
                emailDomains: $emailDomains,
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
        $emailCols = [];
        foreach ($columnAnalyses as $idx => $ca) {
            if ($ca->isIdentifier) {
                continue;
            }
            if ($ca->isEmail) {
                $emailCols[] = ['idx' => $idx, 'analysis' => $ca];
            } elseif (in_array($ca->type, [ColumnType::Integer->value, ColumnType::Decimal->value], true) && $ca->nonEmptyCount > 0) {
                $numericCols[] = ['idx' => $idx, 'analysis' => $ca];
            } elseif (in_array($ca->type, [ColumnType::Text->value, ColumnType::Boolean->value], true) && $ca->nonEmptyCount > 0 && $ca->distinctCount >= 1) {
                $catCols[] = ['idx' => $idx, 'analysis' => $ca];
            } elseif (in_array($ca->type, [ColumnType::Date->value, ColumnType::DateTime->value], true)) {
                $dateCols[] = ['idx' => $idx, 'analysis' => $ca];
            }
        }

        foreach ($emailCols as $ec) {
            $ca = $ec['analysis'];
            if (count($ca->emailDomains) < 2) {
                continue;
            }
            $recs[] = new ChartRecommendation(
                chartType: count($ca->emailDomains) <= 6 ? ChartType::Pie->value : ChartType::Bar->value,
                title: 'Domínios de e-mail — '.$ca->name,
                justification: 'Os endereços foram agrupados por domínio, revelando a origem dos contatos sem criar métricas numéricas artificiais.',
                metrics: ['domains' => count($ca->emailDomains), 'emails' => $ca->nonEmptyCount],
                aggregatedData: $ca->emailDomains,
                columns: [$ca->name],
                issues: [],
            );
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

            if ($ca->isName && $ca->duplicateCount === 0) {
                continue;
            }

            if ($ca->distinctCount >= 2 && $ca->distinctCount <= 20 && $ca->nonEmptyCount >= 2) {
                $aggregated = $ca->categories;
                if ($ca->isEmail || $ca->distinctCount <= 6) {
                    $recs[] = new ChartRecommendation(
                        chartType: ChartType::Pie->value,
                        title: ($ca->isName ? 'Nomes mais frequentes — ' : 'Proporção por ').$ca->name,
                        justification: $ca->isName
                            ? 'Nomes repetidos permitem identificar os contatos mais presentes na base.'
                            : 'Categorias com baixa cardinalidade permitem visualização em pizza.',
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

                continue;
            }

            if (! $ca->isName && $ca->distinctCount > 20 && $ca->distinctCount <= 50 && $ca->duplicateCount > 0 && count($ca->categories) >= 2) {
                $recs[] = new ChartRecommendation(
                    chartType: ChartType::Bar->value,
                    title: 'Valores mais frequentes — '.$ca->name,
                    justification: 'A coluna tem muitos valores distintos; o gráfico destaca apenas os mais recorrentes, sem criar categorias artificiais.',
                    metrics: ['distinct' => $ca->distinctCount, 'total' => $ca->nonEmptyCount],
                    aggregatedData: $ca->categories,
                    columns: [$ca->name],
                    issues: [],
                );
            }
        }

        if (! empty($catCols) && ! empty($numericCols) && $totalRecords >= 2) {
            foreach ($catCols as $cc) {
                if ($cc['analysis']->isName || $cc['analysis']->distinctCount < 2 || $cc['analysis']->distinctCount > 15) {
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

    /**
     * @param  array<int, ColumnAnalysis>  $columnAnalyses
     * @param  array<int, ChartRecommendation>  $recommendations
     * @return array<string, mixed>
     */
    private function buildSummary(StoredImport $import, array $columnAnalyses, array $recommendations, int $totalRecords): array
    {
        $filled = 0;
        $empty = 0;
        $numeric = 0;
        $text = 0;
        $date = 0;
        $boolean = 0;
        $email = 0;
        $name = 0;
        $categorical = 0;
        $measures = [];
        $dateMin = null;
        $dateMax = null;
        $domains = [];

        foreach ($columnAnalyses as $ca) {
            $filled += $ca->nonEmptyCount;
            $empty += $ca->emptyCount;

            if (in_array($ca->type, [ColumnType::Integer->value, ColumnType::Decimal->value], true)) {
                $numeric++;
                if (! $ca->isIdentifier && $ca->nonEmptyCount > 0) {
                    $measures[] = [
                        'name' => $ca->name,
                        'sum' => $ca->sum,
                        'mean' => $ca->mean,
                        'min' => $ca->min,
                        'max' => $ca->max,
                    ];
                }
            }

            if (in_array($ca->type, [ColumnType::Text->value, ColumnType::Mixed->value], true)) {
                $text++;
            }

            if (in_array($ca->type, [ColumnType::Date->value, ColumnType::DateTime->value], true)) {
                $date++;
                if ($ca->min !== null && ($dateMin === null || strcmp((string) $ca->min, $dateMin) < 0)) {
                    $dateMin = (string) $ca->min;
                }
                if ($ca->max !== null && ($dateMax === null || strcmp((string) $ca->max, $dateMax) > 0)) {
                    $dateMax = (string) $ca->max;
                }
            }

            if ($ca->type === ColumnType::Boolean->value) {
                $boolean++;
            }

            if ($ca->isEmail) {
                $email++;
                foreach ($ca->emailDomains as $domain) {
                    $value = (string) ($domain['value'] ?? '');
                    if ($value === '') {
                        continue;
                    }
                    $domains[$value] = ($domains[$value] ?? 0) + (int) ($domain['count'] ?? 0);
                }
            }

            if ($ca->isName) {
                $name++;
            }

            if (! $ca->isIdentifier && in_array($ca->type, [ColumnType::Text->value, ColumnType::Boolean->value], true)) {
                $categorical++;
            }
        }

        arsort($domains);
        $domainList = [];
        foreach ($domains as $value => $count) {
            $domainList[] = ['value' => $value, 'count' => $count];
        }

        $totalColumns = count($import->columns);
        $totalCells = $totalRecords * $totalColumns;

        return [
            'total_records' => $totalRecords,
            'total_columns' => $totalColumns,
            'recommendations_count' => count($recommendations),
            'filled_cells' => $filled,
            'empty_cells' => $empty,
            'total_cells' => $totalCells,
            'completeness' => $totalCells > 0 ? round(($filled / $totalCells) * 100, 1) : 0.0,
            'numeric_columns' => $numeric,
            'text_columns' => $text,
            'date_columns' => $date,
            'boolean_columns' => $boolean,
            'email_columns' => $email,
            'name_columns' => $name,
            'categorical_columns' => $categorical,
            'email_domains' => $domainList,
            'measures' => array_slice($measures, 0, 6),
            'date_range' => $dateMin !== null && $dateMax !== null ? ['min' => $dateMin, 'max' => $dateMax] : null,
        ];
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
            $data = $analysis->toArray();
            $data['analysis_version'] = self::ANALYSIS_VERSION;
            $encoded = json_encode(
                $data,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
            $disk->put($importId.'/analysis.json', $encoded);
        } catch (Throwable) {
        }
    }

    private function disk(): Filesystem
    {
        return Storage::disk((string) config('datacanvas.disk', 'imports'));
    }
}
