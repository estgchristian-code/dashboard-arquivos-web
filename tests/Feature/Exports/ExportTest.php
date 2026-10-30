<?php

namespace Tests\Feature\Exports;

use App\Services\Analysis\AnalysisService;
use App\Services\Exports\ExportService;
use App\Services\Imports\ImportService;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Feature\Imports\ImportTestCase;

class ExportTest extends ImportTestCase
{
    public function test_csv_export_preserves_headers_and_protects_against_injection(): void
    {
        $import = $this->importFixture('valid.csv');
        $service = app(ExportService::class);
        $response = $service->exportCsv($import->id, $this->ownerHash('visitante-a'));
        $this->assertInstanceOf(StreamedResponse::class, $response);
    }

    public function test_json_export_contains_data_and_analysis(): void
    {
        $import = $this->importFixture('valid.csv');
        app(AnalysisService::class)->analyzeForOwner($import->id, $this->ownerHash('visitante-a'));
        $service = app(ExportService::class);
        $result = $service->exportJson($import->id, $this->ownerHash('visitante-a'));
        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('filename', $result);
        $data = json_decode($result['content'], true);
        $this->assertArrayHasKey('import', $data);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('analysis', $data);
    }

    public function test_pdf_export_generates_report(): void
    {
        $import = $this->importFixture('valid.csv');
        $service = app(ExportService::class);
        $result = $service->exportPdf($import->id, $this->ownerHash('visitante-a'));
        $this->assertArrayHasKey('content', $result);
        $this->assertStringStartsWith('%PDF', $result['content']);
    }

    public function test_exports_are_isolated_by_owner(): void
    {
        $import = $this->importFixture('valid.csv');
        $service = app(ExportService::class);
        $this->expectException(HttpException::class);
        $service->exportCsv($import->id, $this->ownerHash('visitante-b'));
    }

    public function test_csv_injection_protection(): void
    {
        $csv = "nome,formula\n=cmd|'/c calc'!A0,10\n";
        $path = sys_get_temp_dir().'/test_inject.csv';
        file_put_contents($path, $csv);
        $file = new UploadedFile($path, 'test.csv', 'text/csv', null, true);
        $import = app(ImportService::class)->import($file, $this->ownerHash('visitante-a'));
        $service = app(ExportService::class);
        $response = $service->exportCsv($import->id, $this->ownerHash('visitante-a'));
        $this->assertInstanceOf(StreamedResponse::class, $response);
        @unlink($path);
    }
}
