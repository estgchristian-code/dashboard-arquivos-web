<?php

namespace App\Services\Analysis\Data;

final readonly class ColumnAnalysis
{
    public function __construct(
        public string $name,
        public string $type,
        public bool $isIdentifier = false,
        public int $totalCount = 0,
        public int $nonEmptyCount = 0,
        public int $emptyCount = 0,
        public int $distinctCount = 0,
        public int $duplicateCount = 0,
        public mixed $min = null,
        public mixed $max = null,
        public mixed $sum = null,
        public float|int|null $mean = null,
        public float|int|null $median = null,
        public array $categories = [],
        public bool $hasTemporalTrend = false,
        public bool $isEmail = false,
        public bool $isName = false,
        public array $emailDomains = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'is_identifier' => $this->isIdentifier,
            'total_count' => $this->totalCount,
            'non_empty_count' => $this->nonEmptyCount,
            'empty_count' => $this->emptyCount,
            'distinct_count' => $this->distinctCount,
            'duplicate_count' => $this->duplicateCount,
            'min' => $this->min,
            'max' => $this->max,
            'sum' => $this->sum,
            'mean' => $this->mean,
            'median' => $this->median,
            'categories' => $this->categories,
            'has_temporal_trend' => $this->hasTemporalTrend,
            'is_email' => $this->isEmail,
            'is_name' => $this->isName,
            'email_domains' => $this->emailDomains,
        ];
    }
}
