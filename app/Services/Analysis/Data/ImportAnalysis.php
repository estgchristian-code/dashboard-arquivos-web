<?php

namespace App\Services\Analysis\Data;

final readonly class ImportAnalysis
{
    /**
     * @param  array<int, ColumnAnalysis>  $columnAnalyses
     * @param  array<int, ChartRecommendation>  $recommendations
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>  $issues
     */
    public function __construct(
        public string $importId,
        public int $totalRecords,
        public array $columnAnalyses,
        public array $recommendations,
        public array $summary = [],
        public array $issues = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'import_id' => $this->importId,
            'total_records' => $this->totalRecords,
            'column_analyses' => array_map(fn (ColumnAnalysis $analysis): array => $analysis->toArray(), $this->columnAnalyses),
            'recommendations' => array_map(fn (ChartRecommendation $recommendation): array => $recommendation->toArray(), $this->recommendations),
            'summary' => $this->summary,
            'issues' => $this->issues,
        ];
    }
}
