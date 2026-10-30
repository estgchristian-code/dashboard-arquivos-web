<?php

namespace App\Services\Imports\Support;

use App\Exceptions\ImportException;
use ZipArchive;

final class ZipGuard
{
    public function inspect(string $path): void
    {
        $archive = new ZipArchive;

        if ($archive->open($path, ZipArchive::RDONLY) !== true) {
            throw ImportException::corruptSpreadsheet();
        }

        try {
            $maxEntries = (int) config('datacanvas.limits.max_zip_entries');
            $maxEntryBytes = (int) config('datacanvas.limits.max_zip_entry_bytes');
            $maxUncompressedBytes = (int) config('datacanvas.limits.max_zip_uncompressed_bytes');
            $maxRatio = (int) config('datacanvas.limits.max_zip_compression_ratio');

            if ($archive->numFiles < 1 || $archive->numFiles > $maxEntries) {
                throw ImportException::unsafeArchive();
            }

            $totalUncompressed = 0;
            $names = [];

            for ($index = 0; $index < $archive->numFiles; $index++) {
                $stat = $archive->statIndex($index);

                if ($stat === false) {
                    throw ImportException::corruptSpreadsheet();
                }

                $name = (string) $stat['name'];
                $size = (int) $stat['size'];
                $compressedSize = (int) $stat['comp_size'];

                if ($this->unsafeName($name)) {
                    throw ImportException::unsafeArchive();
                }

                if ((($stat['flags'] ?? 0) & 1) === 1 || (($stat['encryption_method'] ?? 0) & 0xFFFF) !== 0) {
                    throw ImportException::unsafeArchive();
                }

                if ($size > $maxEntryBytes) {
                    throw ImportException::unsafeArchive();
                }

                if ($size > 0 && $compressedSize === 0) {
                    throw ImportException::unsafeArchive();
                }

                if ($compressedSize > 0 && ($size / $compressedSize) > $maxRatio) {
                    throw ImportException::unsafeArchive();
                }

                if ($this->isExecutableEntry($name)) {
                    throw ImportException::embeddedContent();
                }

                $totalUncompressed += $size;

                if ($totalUncompressed > $maxUncompressedBytes) {
                    throw ImportException::unsafeArchive();
                }

                $names[] = $name;
            }

            if (! in_array('[Content_Types].xml', $names, true) || ! in_array('xl/workbook.xml', $names, true)) {
                throw ImportException::corruptSpreadsheet();
            }

            foreach ($names as $index => $name) {
                if (! preg_match('/\.(xml|rels)$/i', $name)) {
                    continue;
                }

                $prefix = $archive->getFromIndex($index, 8192);

                if ($prefix === false) {
                    throw ImportException::corruptSpreadsheet();
                }

                if (preg_match('/<!DOCTYPE|<!ENTITY/i', $prefix)) {
                    throw ImportException::unsafeArchive();
                }
            }

            $contentTypesIndex = array_search('[Content_Types].xml', $names, true);

            if ($contentTypesIndex !== false) {
                $contentTypes = $archive->getFromIndex($contentTypesIndex, 1024 * 1024);

                if ($contentTypes === false) {
                    throw ImportException::corruptSpreadsheet();
                }

                // Only reject based on explicit macro-enabled workbook or direct VBA reference
                if (preg_match('/ContentType="application\/vnd\.ms-office\.vbaProject"/i', $contentTypes)
                    || preg_match('/ContentType="application\/vnd\.ms-excel\.sheet\.macroEnabled\.main\+xml"/i', $contentTypes)
                    || preg_match('/PartName="\/xl\/vbaProject\.bin"/i', $contentTypes)
                    || preg_match('/\/xl\/vbaProject\.bin/i', $contentTypes)) {
                    throw ImportException::embeddedContent();
                }

                // Only reject ActiveX/OLE if explicitly declared as such
                if (preg_match('/ContentType="application\/vnd\.ms-office\.activeX"/i', $contentTypes)
                    || preg_match('/ContentType="application\/vnd\.openxmlformats-officedocument\.oleObject"/i', $contentTypes)
                    || preg_match('/PartName="\/xl\/activeX\//i', $contentTypes)
                    || preg_match('/PartName="\/xl\/oleObjects\//i', $contentTypes)) {
                    throw ImportException::embeddedContent();
                }
            }

            foreach ($names as $name) {
                if (preg_match('/^xl\/vbaProject\.bin$/i', $name)) {
                    throw ImportException::embeddedContent();
                }
                if (preg_match('/^xl\/activeX\//i', $name) || preg_match('/^xl\/oleObjects\//i', $name)) {
                    throw ImportException::embeddedContent();
                }
            }
        } finally {
            $archive->close();
        }
    }

    private function unsafeName(string $name): bool
    {
        return $name === ''
            || str_contains($name, "\0")
            || str_contains($name, '\\')
            || str_starts_with($name, '/')
            || preg_match('#(^|/)\.\.(/|$)#', $name) === 1;
    }

    private function isExecutableEntry(string $name): bool
    {
        if (preg_match('/\.(bin|exe|dll|js|vbs|sh|bat|cmd|ps1)$/i', $name) === 1) {
            // Allow only specific non-executable bin files if absolutely necessary? No - block generally
            // But vbaProject.bin handled separately
            if (preg_match('/vbaProject\.bin$/i', $name) === 1) {
                return true;
            }

            // Other .bin files are suspicious
            return true;
        }
        if (preg_match('/vbaProject\.bin$/i', $name) === 1) {
            return true;
        }

        return false;
    }
}
