<?php

namespace Tests\Feature\Analysis;

use App\Services\Analysis\AnalysisService;
use App\Services\Analysis\Data\ImportAnalysis;
use App\Services\Imports\ImportService;
use Illuminate\Http\UploadedFile;
use Tests\Feature\Imports\ImportTestCase;

class DataAnalysisTest extends ImportTestCase
{
    public function test_analyzes_csv_with_statistics_and_recommendations(): void
    {
        $import = $this->importFixture('valid.csv');
        $service = app(AnalysisService::class);
        $analysis = $service->analyzeForOwner($import->id, $this->ownerHash('visitante-a'));

        $this->assertInstanceOf(ImportAnalysis::class, $analysis);
        $this->assertSame($import->id, $analysis->importId);
        $this->assertSame(4, $analysis->totalRecords);

        $this->assertCount(7, $analysis->columnAnalyses);
        $quant = null;
        foreach ($analysis->columnAnalyses as $ca) {
            if ($ca->name === 'Quantidade') {
                $quant = $ca;
                break;
            }
        }
        $this->assertNotNull($quant);
        $this->assertSame('integer', $quant->type);
        $this->assertFalse($quant->isIdentifier);
        $this->assertEquals(22, $quant->sum);
        $this->assertNotNull($quant->mean);
        $this->assertNotNull($quant->min);
        $this->assertNotNull($quant->max);

        $this->assertNotEmpty($analysis->recommendations);
        $types = array_map(fn ($r) => $r->chartType, $analysis->recommendations);
        $valid = ['bar', 'line', 'area', 'pie', 'scatter', 'histogram'];
        foreach ($types as $t) {
            $this->assertContains($t, $valid);
        }
        foreach ($analysis->recommendations as $rec) {
            $this->assertNotEmpty($rec->title);
            $this->assertNotEmpty($rec->justification);
            $this->assertIsArray($rec->aggregatedData);
        }

        $stored = $service->getAnalysis($import->id, $this->ownerHash('visitante-a'));
        $this->assertInstanceOf(ImportAnalysis::class, $stored);
    }

    public function test_identifies_identifiers_and_does_not_treat_as_measure(): void
    {
        $csv = "id,valor\n1,10\n2,20\n3,30\n";
        $path = sys_get_temp_dir().'/test_ids.csv';
        file_put_contents($path, $csv);
        $file = new UploadedFile($path, 'test.csv', 'text/csv', null, true);
        $import = app(ImportService::class)->import($file, $this->ownerHash('visitante-a'));

        $service = app(AnalysisService::class);
        $analysis = $service->analyzeForOwner($import->id, $this->ownerHash('visitante-a'));

        foreach ($analysis->columnAnalyses as $ca) {
            if ($ca->name === 'id') {
                $this->assertTrue($ca->isIdentifier);
                break;
            }
        }
        @unlink($path);
    }

    public function test_histogram_recommended_for_numeric_distribution(): void
    {
        $csv = "valor\n1\n2\n2\n3\n4\n5\n";
        $path = sys_get_temp_dir().'/test_hist.csv';
        file_put_contents($path, $csv);
        $file = new UploadedFile($path, 'test.csv', 'text/csv', null, true);
        $import = app(ImportService::class)->import($file, $this->ownerHash('visitante-a'));

        $service = app(AnalysisService::class);
        $analysis = $service->analyzeForOwner($import->id, $this->ownerHash('visitante-a'));

        $types = array_map(fn ($r) => $r->chartType, $analysis->recommendations);
        $this->assertContains('histogram', $types);
        @unlink($path);
    }

    public function test_pie_for_small_categories_bar_for_larger(): void
    {
        $csv = "cat,valor\nA,10\nB,20\nC,30\n";
        $path = sys_get_temp_dir().'/test_cat_small.csv';
        file_put_contents($path, $csv);
        $file = new UploadedFile($path, 'test.csv', 'text/csv', null, true);
        $import = app(ImportService::class)->import($file, $this->ownerHash('visitante-a'));
        $service = app(AnalysisService::class);
        $analysis = $service->analyzeForOwner($import->id, $this->ownerHash('visitante-a'));
        $types = array_map(fn ($r) => $r->chartType, $analysis->recommendations);
        $this->assertContains('pie', $types);
        @unlink($path);
    }

    public function test_time_series_recommendations(): void
    {
        $csv = "data,valor\n01/01/2026,10\n02/01/2026,15\n03/01/2026,12\n";
        $path = sys_get_temp_dir().'/test_time.csv';
        file_put_contents($path, $csv);
        $file = new UploadedFile($path, 'test.csv', 'text/csv', null, true);
        $import = app(ImportService::class)->import($file, $this->ownerHash('visitante-a'));
        $service = app(AnalysisService::class);
        $analysis = $service->analyzeForOwner($import->id, $this->ownerHash('visitante-a'));
        $types = array_map(fn ($r) => $r->chartType, $analysis->recommendations);
        $this->assertTrue(in_array('line', $types) || in_array('area', $types));
        @unlink($path);
    }

    public function test_scatter_for_two_numerics(): void
    {
        $csv = "x,y\n1,2\n2,4\n3,6\n4,8\n";
        $path = sys_get_temp_dir().'/test_scatter.csv';
        file_put_contents($path, $csv);
        $file = new UploadedFile($path, 'test.csv', 'text/csv', null, true);
        $import = app(ImportService::class)->import($file, $this->ownerHash('visitante-a'));
        $service = app(AnalysisService::class);
        $analysis = $service->analyzeForOwner($import->id, $this->ownerHash('visitante-a'));
        $types = array_map(fn ($r) => $r->chartType, $analysis->recommendations);
        $this->assertContains('scatter', $types);
        @unlink($path);
    }

    public function test_insufficient_data_detection(): void
    {
        $csv = "a\n1\n";
        $path = sys_get_temp_dir().'/test_small.csv';
        file_put_contents($path, $csv);
        $file = new UploadedFile($path, 'test.csv', 'text/csv', null, true);
        $import = app(ImportService::class)->import($file, $this->ownerHash('visitante-a'));
        $service = app(AnalysisService::class);
        $analysis = $service->analyzeForOwner($import->id, $this->ownerHash('visitante-a'));
        $this->assertNotEmpty($analysis->issues);
        @unlink($path);
    }

    public function test_analysis_stored_in_private_storage_isolated_by_owner(): void
    {
        $import = $this->importFixture('valid.csv');
        $service = app(AnalysisService::class);
        $analysis = $service->analyzeForOwner($import->id, $this->ownerHash('visitante-a'));
        $this->assertInstanceOf(ImportAnalysis::class, $analysis);

        $other = $service->getAnalysis($import->id, $this->ownerHash('visitante-b'));
        $this->assertNull($other);
    }

    public function test_text_only_names_and_emails_generate_dashboard(): void
    {
        $csv = "Nome,Email\nJoao Silva,joao@exemplo.com\nMaria Santos,maria@gmail.com\nPedro Costa,pedro@exemplo.com\nAna Lima,ana@gmail.com\n";
        $path = sys_get_temp_dir().'/ne2.csv';
        file_put_contents($path, $csv);
        $file = new UploadedFile($path, 'ne2.csv', 'text/csv', null, true);
        $import = app(ImportService::class)->import($file, $this->ownerHash('visitante-a'));
        $service = app(AnalysisService::class);
        $analysis = $service->analyzeForOwner($import->id, $this->ownerHash('visitante-a'));

        $this->assertInstanceOf(ImportAnalysis::class, $analysis);
        $this->assertGreaterThanOrEqual(1, count($analysis->recommendations));
        $types = array_map(fn ($r) => $r->chartType, $analysis->recommendations);
        $this->assertContains('pie', $types);
        @unlink($path);
    }

    public function test_text_categorical_only(): void
    {
        $csv = "Categoria\nA\nB\nA\nC\nB\n";
        $path = sys_get_temp_dir().'/cat.csv';
        file_put_contents($path, $csv);
        $file = new UploadedFile($path, 'cat.csv', 'text/csv', null, true);
        $import = app(ImportService::class)->import($file, $this->ownerHash('visitante-a'));
        $service = app(AnalysisService::class);
        $analysis = $service->analyzeForOwner($import->id, $this->ownerHash('visitante-a'));
        $this->assertGreaterThanOrEqual(1, count($analysis->recommendations));
        @unlink($path);
    }

    public function test_single_column_insufficient_or_generates_if_possible(): void
    {
        $csv = "Valor\nA\nB\nC\n";
        $path = sys_get_temp_dir().'/single.csv';
        file_put_contents($path, $csv);
        $file = new UploadedFile($path, 'single.csv', 'text/csv', null, true);
        $import = app(ImportService::class)->import($file, $this->ownerHash('visitante-a'));
        $service = app(AnalysisService::class);
        $analysis = $service->analyzeForOwner($import->id, $this->ownerHash('visitante-a'));
        // May or may not generate recommendations depending on distinct count, but should not crash
        $this->assertInstanceOf(ImportAnalysis::class, $analysis);
        @unlink($path);
    }
}
