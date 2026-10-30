<?php

namespace App\Services\Imports\Data;

use App\Enums\ColumnType;

final readonly class ColumnProfile
{
    public function __construct(
        public string $name,
        public ColumnType $type,
        public int $emptyCount,
    ) {}

    /**
     * @return array{name: string, type: string, type_label: string, empty_count: int}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'empty_count' => $this->emptyCount,
        ];
    }
}
