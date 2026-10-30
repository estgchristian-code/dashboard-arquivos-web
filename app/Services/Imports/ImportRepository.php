<?php

namespace App\Services\Imports;

use App\Enums\ColumnType;
use App\Enums\ImportFormat;
use App\Exceptions\ImportException;
use App\Services\Imports\Data\ParsedTable;
use App\Services\Imports\Data\StoredImport;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Persistência temporária das importações em disco privado.
 *
 * Cada importação ocupa um diretório com nome aleatório contendo:
 * metadata.json (dados de exibição), preview.json (amostra), data.json
 * (linhas normalizadas) e o arquivo original com nome aleatório.
 *
 * Não há banco de dados nem URL pública. O acesso é sempre autorizado
 * pelo hash do token do visitante gravado no metadata.
 */
final class ImportRepository
{
    private const VERSION = 1;

    public function create(
        ParsedTable $table,
        string $originalName,
        int $sizeBytes,
        string $ownerHash,
        ?string $sourcePath = null,
        ?string $sourceExtension = null,
    ): StoredImport {
        $id = bin2hex(random_bytes(16));
        $previewRows = array_slice($table->rows, 0, max(1, (int) config('datacanvas.preview_rows')));
        $createdAt = Carbon::now();
        $expiresAt = $createdAt->copy()->addHours(max(1, (int) config('datacanvas.retention_hours')));

        $sourceFile = null;

        if ($sourcePath !== null && is_file($sourcePath)) {
            $extension = preg_replace('/[^a-z0-9]/', '', strtolower((string) $sourceExtension)) ?: $table->format->extension();
            $sourceFile = bin2hex(random_bytes(16)).'.'.$extension;
        }

        $metadata = [
            'version' => self::VERSION,
            'id' => $id,
            'original_name' => $this->safeOriginalName($originalName, $table->format),
            'format' => $table->format->value,
            'source_file' => $sourceFile,
            'owner_hash' => $ownerHash,
            'size_bytes' => $sizeBytes,
            'row_count' => $table->rowCount,
            'column_count' => $table->columnCount,
            'empty_cells' => $table->emptyCells,
            'encoding' => $table->encoding,
            'delimiter' => $table->delimiter,
            'sheet_name' => $table->sheetName,
            'columns' => array_map(fn ($column): array => $column->toArray(), $table->columns),
            'created_at' => $createdAt->toIso8601String(),
            'expires_at' => $expiresAt->toIso8601String(),
        ];

        $disk = $this->disk();

        try {
            $disk->makeDirectory($id);

            $this->writeJson($disk, $id.'/data.json', ['rows' => $table->rows]);
            $this->writeJson($disk, $id.'/preview.json', ['rows' => $previewRows]);

            if ($sourceFile !== null && $sourcePath !== null) {
                $stream = fopen($sourcePath, 'rb');

                if ($stream === false) {
                    throw ImportException::storageFailure();
                }

                try {
                    $disk->writeStream($id.'/'.$sourceFile, $stream);
                } finally {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }
            }

            // metadata.json é gravado por último: sua presença indica importação completa.
            $this->writeJson($disk, $id.'/metadata.json', $metadata);
        } catch (Throwable $exception) {
            try {
                $disk->deleteDirectory($id);
            } catch (Throwable) {
                // Falha de limpeza não deve mascarar o erro original.
            }

            if ($exception instanceof ImportException) {
                throw $exception;
            }

            throw ImportException::storageFailure();
        }

        return $this->hydrate($metadata, $previewRows);
    }

    public function findOwned(string $id, string $ownerHash): ?StoredImport
    {
        if (! $this->isValidId($id)) {
            return null;
        }

        $metadata = $this->readMetadata($id);

        if ($metadata === null) {
            return null;
        }

        if (! is_string($metadata['owner_hash'] ?? null) || ! hash_equals($metadata['owner_hash'], $ownerHash)) {
            return null;
        }

        $expiresAt = $this->parseDate($metadata['expires_at'] ?? null);
        $createdAt = $this->parseDate($metadata['created_at'] ?? null);

        if ($expiresAt === null || $createdAt === null) {
            $this->delete($id);

            return null;
        }

        if ($expiresAt->isPast()) {
            $this->delete($id);

            return null;
        }

        $preview = $this->readJson($this->disk(), $id.'/preview.json');
        $rows = is_array($preview['rows'] ?? null) ? $preview['rows'] : [];
        $rows = array_map(
            fn (mixed $row): array => array_map(
                fn (mixed $value): string => is_scalar($value) ? (string) $value : '',
                is_array($row) ? $row : [],
            ),
            array_values(array_filter($rows, 'is_array')),
        );

        try {
            return $this->hydrate($metadata, $rows);
        } catch (Throwable) {
            $this->delete($id);

            return null;
        }
    }

    public function deleteOwned(string $id, string $ownerHash): bool
    {
        $import = $this->findOwned($id, $ownerHash);

        if ($import === null) {
            return false;
        }

        $this->delete($id);

        return true;
    }

    public function delete(string $id): void
    {
        if (! $this->isValidId($id)) {
            return;
        }

        try {
            $this->disk()->deleteDirectory($id);
        } catch (Throwable) {
            // A limpeza é best-effort; a próxima execução tenta novamente.
        }
    }

    /**
     * Remove importações expiradas e diretórios incompletos antigos.
     *
     * @return int quantidade de diretórios removidos
     */
    public function cleanupExpired(?Carbon $now = null): int
    {
        $now ??= Carbon::now();
        $disk = $this->disk();
        $removed = 0;

        try {
            $directories = $disk->directories();
        } catch (Throwable) {
            return 0;
        }

        foreach ($directories as $directory) {
            $id = basename(str_replace('\\', '/', $directory));

            if (! $this->isValidId($id)) {
                $disk->deleteDirectory($directory);
                $removed++;

                continue;
            }

            $metadata = $this->readMetadata($id);

            if ($metadata === null) {
                // Diretório sem metadata: importação interrompida. Remove após 1 hora.
                if ($this->directoryAgeInHours($disk, $id) >= 1) {
                    $disk->deleteDirectory($id);
                    $removed++;
                }

                continue;
            }

            $expiresAt = $this->parseDate($metadata['expires_at'] ?? null);

            if ($expiresAt === null || $expiresAt->lessThanOrEqualTo($now)) {
                $disk->deleteDirectory($id);
                $removed++;
            }
        }

        return $removed;
    }

    private function hydrate(array $metadata, array $rows): StoredImport
    {
        $format = ImportFormat::tryFrom((string) ($metadata['format'] ?? ''));

        if ($format === null) {
            throw ImportException::storageFailure();
        }

        $createdAt = $this->parseDate($metadata['created_at'] ?? null);
        $expiresAt = $this->parseDate($metadata['expires_at'] ?? null);

        if ($createdAt === null || $expiresAt === null) {
            throw ImportException::storageFailure();
        }

        $columns = [];

        foreach ((array) ($metadata['columns'] ?? []) as $column) {
            if (! is_array($column) || ! is_string($column['name'] ?? null)) {
                continue;
            }

            $type = ColumnType::tryFrom((string) ($column['type'] ?? '')) ?? ColumnType::Mixed;
            $columns[] = [
                'name' => $column['name'],
                'type' => $type->value,
                'type_label' => $type->label(),
                'empty_count' => max(0, (int) ($column['empty_count'] ?? 0)),
            ];
        }

        return new StoredImport(
            id: (string) $metadata['id'],
            originalName: (string) $metadata['original_name'],
            format: $format,
            sizeBytes: max(0, (int) ($metadata['size_bytes'] ?? 0)),
            rowCount: max(0, (int) ($metadata['row_count'] ?? 0)),
            columnCount: max(0, (int) ($metadata['column_count'] ?? 0)),
            emptyCells: max(0, (int) ($metadata['empty_cells'] ?? 0)),
            encoding: is_string($metadata['encoding'] ?? null) ? $metadata['encoding'] : 'UTF-8',
            delimiter: is_string($metadata['delimiter'] ?? null) ? $metadata['delimiter'] : null,
            sheetName: is_string($metadata['sheet_name'] ?? null) ? $metadata['sheet_name'] : null,
            columns: $columns,
            previewRows: $rows,
            createdAt: $createdAt,
            expiresAt: $expiresAt,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readMetadata(string $id): ?array
    {
        $metadata = $this->readJson($this->disk(), $id.'/metadata.json');

        if ($metadata === null || ! is_string($metadata['id'] ?? null) || ! hash_equals($id, $metadata['id'])) {
            return null;
        }

        if ((int) ($metadata['version'] ?? 0) !== self::VERSION) {
            return null;
        }

        return $metadata;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readJson(Filesystem $disk, string $path): ?array
    {
        try {
            if (! $disk->exists($path)) {
                return null;
            }

            $contents = $disk->get($path);
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function writeJson(Filesystem $disk, string $path, array $data): void
    {
        $encoded = json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE,
        );

        if (! $disk->put($path, $encoded)) {
            throw ImportException::storageFailure();
        }
    }

    private function safeOriginalName(string $name, ImportFormat $format): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = (string) preg_replace('/[\x00-\x1F\x7F]+/u', '', $name);
        $name = trim($name);

        if ($name === '' || $name === '.' || $name === '..') {
            return 'importacao.'.$format->extension();
        }

        return mb_substr($name, 0, 160);
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function directoryAgeInHours(Filesystem $disk, string $id): float
    {
        try {
            $lastModified = $disk->lastModified($id);
        } catch (Throwable) {
            return 0.0;
        }

        return $lastModified > 0 ? (time() - $lastModified) / 3600 : 0.0;
    }

    private function isValidId(string $id): bool
    {
        return preg_match('/^[a-f0-9]{32}$/', $id) === 1;
    }

    private function disk(): Filesystem
    {
        return Storage::disk((string) config('datacanvas.disk', 'imports'));
    }
}
