<?php

namespace App\Services\Imports\Support;

use App\Enums\ImportFormat;
use App\Exceptions\ImportException;

final class FormatDetector
{
    public function __construct(private readonly ZipGuard $zipGuard) {}

    public function detect(string $path, string $extension): ImportFormat
    {
        $expected = ImportFormat::fromExtension($extension);

        if ($expected === null) {
            throw ImportException::unsupportedExtension();
        }

        $content = @file_get_contents($path);

        if ($content === false) {
            throw ImportException::corruptSpreadsheet();
        }

        $isZip = str_starts_with($content, "PK\x03\x04");
        $isOle = str_starts_with($content, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1");

        if ($expected === ImportFormat::Xlsx) {
            if (! $isZip) {
                throw ImportException::formatMismatch();
            }

            $this->zipGuard->inspect($path);

            return ImportFormat::Xlsx;
        }

        if ($expected === ImportFormat::Xls) {
            if (! $isOle) {
                throw ImportException::formatMismatch();
            }

            return ImportFormat::Xls;
        }

        if ($isZip || $isOle) {
            throw ImportException::formatMismatch();
        }

        if (! TextFile::isProbablyText($content) && ! $this->hasUtf16Bom($content)) {
            throw ImportException::binaryContent();
        }

        $decoded = TextFile::decode($content);

        if (trim($decoded['text']) === '') {
            throw ImportException::emptyFile();
        }

        if ($expected === ImportFormat::Json) {
            $trimmed = ltrim($decoded['text']);

            if (! str_starts_with($trimmed, '{') && ! str_starts_with($trimmed, '[')) {
                throw ImportException::invalidJson();
            }

            return ImportFormat::Json;
        }

        $delimiter = TextFile::detectDelimiter($decoded['text']);

        return $delimiter === "\t" ? ImportFormat::Tsv : ImportFormat::Csv;
    }

    private function hasUtf16Bom(string $content): bool
    {
        return str_starts_with($content, "\xFF\xFE") || str_starts_with($content, "\xFE\xFF");
    }
}
