<?php

namespace App\Services\Imports;

use App\Enums\ImportFormat;
use App\Exceptions\ImportException;
use App\Services\Imports\Data\ParsedTable;
use App\Services\Imports\Data\StoredImport;
use App\Services\Imports\Readers\DelimitedTextReader;
use App\Services\Imports\Readers\JsonTableReader;
use App\Services\Imports\Readers\SpreadsheetReader;
use App\Services\Imports\Readers\TabularReader;
use App\Services\Imports\Support\FormatDetector;
use Illuminate\Http\UploadedFile;

/**
 * Orquestra recebimento, detecção real de formato, leitura e armazenamento.
 *
 * O tipo MIME informado pelo navegador é ignorado. A decisão usa a
 * assinatura binária e o conteúdo do arquivo; a extensão serve apenas
 * para recusar cedo nomes claramente incompatíveis.
 */
final class ImportService
{
    public function __construct(
        private readonly FormatDetector $detector,
        private readonly DelimitedTextReader $delimitedReader,
        private readonly JsonTableReader $jsonReader,
        private readonly SpreadsheetReader $spreadsheetReader,
        private readonly ImportRepository $repository,
    ) {}

    public function import(UploadedFile $file, string $ownerHash): StoredImport
    {
        if (! $file->isValid()) {
            throw ImportException::invalidFormat();
        }

        $path = $file->getRealPath();

        if ($path === false || ! is_file($path)) {
            throw ImportException::invalidFormat();
        }

        $size = (int) $file->getSize();

        if ($size <= 0 || filesize($path) === 0) {
            throw ImportException::emptyFile();
        }

        $maxBytes = (int) config('datacanvas.limits.max_file_bytes');

        if ($maxBytes > 0 && $size > $maxBytes) {
            throw ImportException::tooLarge($maxBytes);
        }

        $extension = $this->extension($file->getClientOriginalName());

        if ($extension === null) {
            throw ImportException::unsupportedExtension();
        }

        $format = $this->detector->detect($path, $extension);

        if (! in_array($extension, $format->allowedExtensions(), true)) {
            throw ImportException::formatMismatch();
        }

        $table = $this->readerFor($format)->read($path, $format);

        if (! $table instanceof ParsedTable) {
            throw ImportException::invalidFormat();
        }

        return $this->repository->create(
            table: $table,
            originalName: $file->getClientOriginalName(),
            sizeBytes: $size,
            ownerHash: $ownerHash,
            sourcePath: $path,
            sourceExtension: $format->isText() ? $extension : $format->extension(),
        );
    }

    private function readerFor(ImportFormat $format): TabularReader
    {
        return match ($format) {
            ImportFormat::Csv, ImportFormat::Tsv => $this->delimitedReader,
            ImportFormat::Json => $this->jsonReader,
            ImportFormat::Xlsx, ImportFormat::Xls => $this->spreadsheetReader,
        };
    }

    private function extension(string $originalName): ?string
    {
        $name = basename(str_replace('\\', '/', $originalName));
        $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));

        if ($extension === '' || preg_match('/^[a-z0-9]{1,10}$/', $extension) !== 1) {
            return null;
        }

        return $extension;
    }
}
