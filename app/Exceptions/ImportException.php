<?php

namespace App\Exceptions;

use RuntimeException;

class ImportException extends RuntimeException
{
    public static function invalidFormat(): self
    {
        return new self('Envie um arquivo CSV, TSV, XLSX, XLS ou JSON tabular.');
    }

    public static function unsupportedExtension(): self
    {
        return new self('A extensão do arquivo não é suportada. Use CSV, TSV, XLSX, XLS ou JSON.');
    }

    public static function formatMismatch(): self
    {
        return new self('O conteúdo do arquivo não corresponde à extensão informada.');
    }

    public static function emptyFile(): self
    {
        return new self('O arquivo está vazio.');
    }

    public static function noDataRows(): self
    {
        return new self('O arquivo não contém linhas de dados para importar.');
    }

    public static function tooLarge(int $maxBytes): self
    {
        return new self('O arquivo excede o limite de '.self::formatBytes($maxBytes).'.');
    }

    public static function tooManyRows(int $maxRows): self
    {
        return new self('O arquivo contém mais de '.number_format($maxRows, 0, ',', '.').' linhas.');
    }

    public static function tooManyColumns(int $maxColumns): self
    {
        return new self('O arquivo contém mais de '.number_format($maxColumns, 0, ',', '.').' colunas.');
    }

    public static function tooManyCells(int $maxCells): self
    {
        return new self('O arquivo contém mais de '.number_format($maxCells, 0, ',', '.').' células.');
    }

    public static function processingTimeout(): self
    {
        return new self('O processamento do arquivo excedeu o tempo limite. Tente um arquivo menor.');
    }

    public static function unreadableText(): self
    {
        return new self('Não foi possível identificar a codificação de texto do arquivo.');
    }

    public static function binaryContent(): self
    {
        return new self('O conteúdo do arquivo não parece ser textual.');
    }

    public static function corruptSpreadsheet(): self
    {
        return new self('O arquivo parece corrompido ou malformado.');
    }

    public static function unsafeArchive(): self
    {
        return new self('O arquivo contém uma estrutura compactada não permitida.');
    }

    public static function embeddedContent(): self
    {
        return new self('O arquivo contém macros ou conteúdo incorporado não suportado.');
    }

    public static function invalidJson(): self
    {
        return new self('O JSON não é válido.');
    }

    public static function nonTabularJson(): self
    {
        return new self('O JSON deve conter uma lista de registros tabulares.');
    }

    public static function nestedJson(): self
    {
        return new self('O JSON contém valores aninhados. Use uma estrutura tabular plana.');
    }

    public static function rateLimited(int $seconds): self
    {
        return new self('Muitas tentativas de importação. Tente novamente em '.max(1, $seconds).' segundos.');
    }

    public static function storageFailure(): self
    {
        return new self('Não foi possível armazenar a importação. Tente novamente.');
    }

    private static function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return number_format($bytes / (1024 * 1024), 0, ',', '.').' MB';
        }

        return number_format(max(1, (int) ceil($bytes / 1024)), 0, ',', '.').' KB';
    }
}
