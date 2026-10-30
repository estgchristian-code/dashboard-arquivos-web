<?php

namespace App\Services\Exports;

use App\Services\Analysis\AnalysisService;
use App\Services\Imports\ImportRepository;
use App\Services\Imports\VisitorIdentity;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ExportService
{
    public function __construct(
        private readonly ImportRepository $repository,
        private readonly VisitorIdentity $identity,
        private readonly AnalysisService $analysisService,
    ) {}

    public function exportCsv(string $importId, ?string $ownerHash = null): StreamedResponse
    {
        $import = $this->authorize($importId, $ownerHash);
        $rows = $this->loadFullRows($importId);
        $headers = array_map(fn ($c) => $this->sanitizeCsvHeader((string) ($c['name'] ?? '')), $import->columns);

        $filename = $this->safeFilename($import->originalName, 'csv');

        $callback = function () use ($headers, $rows): void {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                $sanitized = [];
                foreach ($row as $value) {
                    $sanitized[] = $this->sanitizeCsvValue((string) $value);
                }
                fputcsv($out, $sanitized);
            }
            fclose($out);
        };

        return response()->streamDownload($callback, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportJson(string $importId, ?string $ownerHash = null): array
    {
        $import = $this->authorize($importId, $ownerHash);
        $rows = $this->loadFullRows($importId);
        $owner = $ownerHash ?? $this->identity->ownerHash();
        $analysis = $this->analysisService->getAnalysis($importId, $owner);

        $data = [
            'import' => [
                'id' => $import->id,
                'original_name' => $import->originalName,
                'format' => $import->format->value,
                'row_count' => $import->rowCount,
                'column_count' => $import->columnCount,
                'columns' => $import->columns,
            ],
            'data' => [
                'headers' => array_map(fn ($c) => $c['name'], $import->columns),
                'rows' => $rows,
            ],
            'analysis' => $analysis ? $analysis->toArray() : null,
        ];

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return [
            'content' => $json,
            'filename' => $this->safeFilename($import->originalName, 'json'),
        ];
    }

    public function exportPdf(string $importId, ?string $ownerHash = null): array
    {
        $import = $this->authorize($importId, $ownerHash);
        $owner = $ownerHash ?? $this->identity->ownerHash();
        $analysis = $this->analysisService->getAnalysis($importId, $owner);
        if ($analysis === null) {
            $analysis = $this->analysisService->analyzeForOwner($importId, $owner);
        }

        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('DataCanvas');
        $pdf->SetAuthor('DataCanvas');
        $pdf->SetTitle('Relatório - '.$import->originalName);
        $pdf->SetMargins(15, 15, 15);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetDefaultMonospacedFont('courier');
        $pdf->SetFont('courier', '', 10);
        $pdf->AddPage();

        $pdf->SetFont('courier', 'B', 14);
        $pdf->Cell(0, 10, 'DataCanvas - Relatório do Dashboard', 0, 1, 'L');
        $pdf->SetFont('courier', '', 9);
        $pdf->Cell(0, 6, 'Arquivo: '.$import->originalName, 0, 1, 'L');
        $pdf->Cell(0, 6, 'Registros: '.number_format($import->rowCount, 0, ',', '.').' | Colunas: '.$import->columnCount, 0, 1, 'L');
        $pdf->Ln(5);

        $pdf->SetFont('courier', 'B', 11);
        $pdf->Cell(0, 8, 'Indicadores principais', 0, 1, 'L');
        $pdf->SetFont('courier', '', 9);
        $pdf->Cell(0, 5, 'Total de registros: '.number_format($import->rowCount, 0, ',', '.'), 0, 1, 'L');
        $pdf->Cell(0, 5, 'Total de colunas: '.$import->columnCount, 0, 1, 'L');
        $pdf->Cell(0, 5, 'Células vazias: '.number_format($import->emptyCells, 0, ',', '.'), 0, 1, 'L');
        $pdf->Ln(3);

        if ($analysis && ! empty($analysis->recommendations)) {
            $pdf->SetFont('courier', 'B', 11);
            $pdf->Cell(0, 8, 'Gráficos recomendados', 0, 1, 'L');
            $pdf->SetFont('courier', '', 8);
            foreach ($analysis->recommendations as $rec) {
                $pdf->Cell(0, 5, '- '.$rec->title.' ('.$rec->chartType.')', 0, 1, 'L');
            }
            $pdf->Ln(3);
        }

        $pdf->SetFont('courier', 'B', 11);
        $pdf->Cell(0, 8, 'Resumo das colunas', 0, 1, 'L');
        $pdf->SetFont('courier', '', 8);
        foreach ($import->columns as $col) {
            $pdf->Cell(0, 4, '  '.($col['name'] ?? '').' - '.($col['type_label'] ?? ''), 0, 1, 'L');
        }

        $content = $pdf->Output('', 'S');

        return [
            'content' => $content,
            'filename' => $this->safeFilename($import->originalName, 'pdf'),
        ];
    }

    private function authorize(string $importId, ?string $ownerHash = null)
    {
        $owner = $ownerHash ?? $this->identity->ownerHash();
        $import = $this->repository->findOwned($importId, $owner);
        if ($import === null) {
            abort(403, 'Não autorizado.');
        }

        return $import;
    }

    private function loadFullRows(string $importId): array
    {
        $disk = Storage::disk((string) config('datacanvas.disk', 'imports'));
        try {
            if (! $disk->exists($importId.'/data.json')) {
                return [];
            }
            $contents = $disk->get($importId.'/data.json');
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($decoded)) {
                return [];
            }
            $rows = $decoded['rows'] ?? [];
            if (! is_array($rows)) {
                return [];
            }
            $normalized = [];
            foreach (array_values($rows) as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $normalized[] = array_map(
                    fn (mixed $v): string => is_scalar($v) ? (string) $v : '',
                    array_values($row),
                );
            }

            return $normalized;
        } catch (\Throwable) {
            return [];
        }
    }

    private function sanitizeCsvHeader(string $header): string
    {
        $header = trim($header);
        if ($header === '') {
            return 'Coluna';
        }

        return $this->sanitizeCsvValue($header);
    }

    private function sanitizeCsvValue(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (preg_match('/^[=+\-@\t\r]/', $value)) {
            return "'".$value;
        }

        return $value;
    }

    private function safeFilename(string $originalName, string $ext): string
    {
        $name = pathinfo($originalName, PATHINFO_FILENAME);
        $name = Str::slug($name);
        if ($name === '') {
            $name = 'exportacao';
        }
        $name = Str::limit($name, 60, '');

        return $name.'-'.date('YmdHis').'.'.$ext;
    }
}
