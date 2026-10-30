<?php

namespace App\Services\Imports\Readers;

use App\Enums\ImportFormat;
use App\Exceptions\ImportException;
use App\Services\Imports\Data\ParsedTable;
use App\Services\Imports\Support\TableNormalizer;
use App\Services\Imports\Support\TextFile;
use JsonException;
use stdClass;

final class JsonTableReader implements TabularReader
{
    public function __construct(private readonly TableNormalizer $normalizer) {}

    public function read(string $path, ImportFormat $format): ParsedTable
    {
        $content = @file_get_contents($path);

        if ($content === false) {
            throw ImportException::corruptSpreadsheet();
        }

        $decoded = TextFile::decode($content);

        try {
            $data = json_decode($decoded['text'], true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (JsonException) {
            throw ImportException::invalidJson();
        }

        if (! is_array($data) || ! array_is_list($data) || $data === []) {
            throw ImportException::nonTabularJson();
        }

        $rows = [];
        $headers = null;

        if ($this->isListOfObjects($data)) {
            $headers = $this->collectHeaders($data);

            foreach ($data as $record) {
                $row = [];

                foreach ($headers as $header) {
                    $row[] = $this->scalarToString($record[$header] ?? null);
                }

                $rows[] = $row;
            }
        } elseif ($this->isListOfLists($data)) {
            foreach ($data as $record) {
                $row = [];

                foreach ($record as $value) {
                    $row[] = $this->scalarToString($value);
                }

                $rows[] = $row;
            }
        } else {
            throw ImportException::nonTabularJson();
        }

        if ($rows === []) {
            throw ImportException::emptyFile();
        }

        return $this->normalizer->normalize(
            rawRows: $rows,
            format: ImportFormat::Json,
            encoding: $decoded['encoding'],
            explicitHeaders: $headers,
        );
    }

    /**
     * @param  array<int, mixed>  $data
     */
    private function isListOfObjects(array $data): bool
    {
        foreach ($data as $record) {
            if (! is_array($record) || array_is_list($record)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, mixed>  $data
     */
    private function isListOfLists(array $data): bool
    {
        foreach ($data as $record) {
            if (! is_array($record) || ! array_is_list($record)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @return array<int, string>
     */
    private function collectHeaders(array $records): array
    {
        $headers = [];
        $seen = [];

        foreach ($records as $record) {
            foreach (array_keys($record) as $key) {
                $key = (string) $key;

                if (! isset($seen[$key])) {
                    $seen[$key] = true;
                    $headers[] = $key;
                }
            }
        }

        return $headers;
    }

    private function scalarToString(mixed $value): string
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

        if (is_string($value)) {
            return $value;
        }

        if ($value instanceof stdClass || is_array($value)) {
            throw ImportException::nestedJson();
        }

        return (string) $value;
    }
}
