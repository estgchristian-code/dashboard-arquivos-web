<?php

namespace App\Services\Analysis\Data;

final readonly class ChartRecommendation
{
    public function __construct(
        public string $chartType,
        public string $title,
        public string $justification,
        public array $metrics = [],
        public array $aggregatedData = [],
        public array $columns = [],
        public array $issues = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'chart_type' => $this->chartType,
            'title' => $this->title,
            'justification' => $this->justification,
            'metrics' => $this->metrics,
            'aggregated_data' => $this->aggregatedData,
            'columns' => $this->columns,
            'issues' => $this->issues,
        ];
    }
}
