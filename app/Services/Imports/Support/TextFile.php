<?php

namespace App\Services\Imports\Support;

use App\Exceptions\ImportException;

final class TextFile
{
    /**
     * Converte o conteúdo para UTF-8 e normaliza as quebras de linha.
     *
     * @return array{text: string, encoding: string}
     */
    public static function decode(string $content): array
    {
        $encoding = 'UTF-8';

        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        } elseif (str_starts_with($content, "\xFF\xFE")) {
            $encoding = 'UTF-16LE';
            $content = mb_convert_encoding(substr($content, 2), 'UTF-8', 'UTF-16LE');
        } elseif (str_starts_with($content, "\xFE\xFF")) {
            $encoding = 'UTF-16BE';
            $content = mb_convert_encoding(substr($content, 2), 'UTF-8', 'UTF-16BE');
        } elseif (! mb_check_encoding($content, 'UTF-8')) {
            $detected = mb_detect_encoding($content, ['Windows-1252', 'ISO-8859-1'], true);

            if ($detected === false) {
                throw ImportException::unreadableText();
            }

            $encoding = $detected;
            $content = mb_convert_encoding($content, 'UTF-8', $detected);
        }

        if (str_contains($content, "\0") || ! mb_check_encoding($content, 'UTF-8')) {
            throw ImportException::unreadableText();
        }

        $content = str_replace(["\r\n", "\r"], "\n", $content);

        return ['text' => $content, 'encoding' => $encoding];
    }

    public static function isProbablyText(string $content): bool
    {
        if ($content === '' || str_contains($content, "\0")) {
            return false;
        }

        $signatures = [
            "\x89PNG\r\n\x1A\n",
            "\xFF\xD8\xFF",
            'GIF87a',
            'GIF89a',
            '%PDF-',
            "PK\x03\x04",
            "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1",
            "\x7FELF",
            'MZ',
        ];

        foreach ($signatures as $signature) {
            if (str_starts_with($content, $signature)) {
                return false;
            }
        }

        $sample = substr($content, 0, 8192);
        $controlCharacters = preg_match_all('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $sample);

        return $controlCharacters !== false && $controlCharacters <= max(1, (int) floor(strlen($sample) / 100));
    }

    public static function detectDelimiter(string $text): string
    {
        $lines = array_slice(
            array_values(array_filter(explode("\n", substr($text, 0, 32768)), fn (string $line): bool => trim($line) !== '')),
            0,
            30,
        );

        if ($lines === []) {
            return ',';
        }

        $bestDelimiter = ',';
        $bestScore = -1;

        foreach ([',', ';', "\t", '|'] as $delimiter) {
            $counts = array_map(
                fn (string $line): int => self::countOutsideQuotes($line, $delimiter),
                $lines,
            );

            $frequencies = array_count_values($counts);
            $modeCount = max(array_keys($frequencies));
            $consistency = $frequencies[$modeCount] ?? 0;
            $score = ($modeCount * 100) + $consistency;

            if ($modeCount > 0 && $score > $bestScore) {
                $bestScore = $score;
                $bestDelimiter = $delimiter;
            }
        }

        return $bestScore > 0 ? $bestDelimiter : ',';
    }

    private static function countOutsideQuotes(string $line, string $delimiter): int
    {
        $count = 0;
        $inQuotes = false;
        $length = strlen($line);

        for ($i = 0; $i < $length; $i++) {
            $character = $line[$i];

            if ($character === '"') {
                if ($inQuotes && ($line[$i + 1] ?? '') === '"') {
                    $i++;

                    continue;
                }

                $inQuotes = ! $inQuotes;

                continue;
            }

            if (! $inQuotes && $character === $delimiter) {
                $count++;
            }
        }

        return $count;
    }
}
