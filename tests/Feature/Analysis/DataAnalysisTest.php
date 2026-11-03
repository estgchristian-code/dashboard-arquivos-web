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

    public function test_detects_email_columns_and_aggregates_domains(): void
    {
        $import = $this->importCsv(
            "Nome,Email\nJoao Silva,joao@exemplo.com\nMaria Santos,maria@gmail.com\nPedro Costa,pedro@exemplo.com\nAna Lima,ana@gmail.com\n",
            'emails.csv',
        );

        $analysis = app(AnalysisService::class)->analyzeForOwner($import->id, $this->ownerHash('visitante-a'));
        $email = $this->columnNamed($analysis, 'Email');

        $this->assertNotNull($email);
        $this->assertTrue($email->isEmail);
        $this->assertFalse($email->isName);
        $this->assertSame(4, $email->nonEmptyCount);
        $this->assertSame(2, $this->domainCount($email->emailDomains, 'exemplo.com'));
        $this->assertSame(2, $this->domainCount($email->emailDomains, 'gmail.com'));

        $this->assertSame(1, $analysis->summary['email_columns']);
        $this->assertCount(2, $analysis->summary['email_domains']);
        $this->assertSame('exemplo.com', $analysis->summary['email_domains'][0]['value']);
        $this->assertSame(2, $analysis->summary['email_domains'][0]['count']);

        $domainCharts = array_values(array_filter(
            $analysis->recommendations,
            fn ($recommendation): bool => str_starts_with($recommendation->title, 'Domínios de e-mail'),
        ));
        $this->assertNotEmpty($domainCharts);
        $this->assertSame(['Email'], $domainCharts[0]->columns);
        $this->assertSame('pie', $domainCharts[0]->chartType);
    }

    public function test_detects_name_columns_and_reports_uniqueness_in_summary(): void
    {
        $import = $this->importCsv(
            "Nome,Cargo\nJoao Silva,Analista\nMaria Santos,Gerente\nJoao Silva,Analista\nAna Lima,Assistente\n",
            'names.csv',
        );

        $analysis = app(AnalysisService::class)->analyzeForOwner($import->id, $this->ownerHash('visitante-a'));
        $name = $this->columnNamed($analysis, 'Nome');

        $this->assertNotNull($name);
        $this->assertTrue($name->isName);
        $this->assertFalse($name->isEmail);
        $this->assertSame(3, $name->distinctCount);
        $this->assertSame(1, $name->duplicateCount);

        $this->assertSame(1, $analysis->summary['name_columns']);
        $this->assertSame(3, $this->uniqueCountForNameColumns($analysis));

        $nameCharts = array_values(array_filter(
            $analysis->recommendations,
            fn ($recommendation): bool => in_array('Nome', $recommendation->columns, true),
        ));
        $this->assertNotEmpty($nameCharts);
        $this->assertSame('Nomes mais frequentes — Nome', $nameCharts[0]->title);
    }

    public function test_unique_names_do_not_generate_name_based_chart(): void
    {
        $import = $this->importCsv("Nome\nAna Lima\nBruno Souza\nCarla Dias\n", 'unique-names.csv');

        $analysis = app(AnalysisService::class)->analyzeForOwner($import->id, $this->ownerHash('visitante-a'));
        $name = $this->columnNamed($analysis, 'Nome');

        $this->assertNotNull($name);
        $this->assertTrue($name->isName);
        $this->assertSame(0, $name->duplicateCount);
        $this->assertSame(0, $analysis->summary['recommendations_count']);

        foreach ($analysis->recommendations as $recommendation) {
            $this->assertNotContains('Nome', $recommendation->columns);
        }
    }

    public function test_summary_contains_real_kpis_for_numeric_and_temporal_data(): void
    {
        $import = $this->importCsv(
            "Data,Valor\n01/01/2026,10.5\n02/01/2026,20\n03/01/2026,30\n",
            'kpis.csv',
        );

        $analysis = app(AnalysisService::class)->analyzeForOwner($import->id, $this->ownerHash('visitante-a'));
        $summary = $analysis->summary;

        $this->assertSame(3, $summary['total_records']);
        $this->assertSame(2, $summary['total_columns']);
        $this->assertSame(6, $summary['filled_cells']);
        $this->assertSame(0, $summary['empty_cells']);
        $this->assertSame(6, $summary['total_cells']);
        $this->assertSame(100.0, $summary['completeness']);
        $this->assertSame(1, $summary['numeric_columns']);
        $this->assertSame(1, $summary['date_columns']);
        $this->assertSame(['min' => '2026-01-01', 'max' => '2026-01-03'], $summary['date_range']);

        $this->assertCount(1, $summary['measures']);
        $this->assertSame('Valor', $summary['measures'][0]['name']);
        $this->assertEqualsWithDelta(60.5, $summary['measures'][0]['sum'], 0.0001);
        $this->assertEqualsWithDelta(20.166666, $summary['measures'][0]['mean'], 0.0001);
    }

    public function test_analyze_rows_recalculates_filtered_subset_without_touching_cache(): void
    {
        $import = $this->importCsv(
            "Nome,Email,Valor\nJoao,joao@exemplo.com,10\nMaria,maria@gmail.com,20\nPedro,pedro@exemplo.com,30\nAna,ana@gmail.com,40\n",
            'filtered.csv',
        );

        $service = app(AnalysisService::class);
        $full = $service->analyzeForOwner($import->id, $this->ownerHash('visitante-a'));
        $this->assertSame(4, $full->totalRecords);
        $this->assertSame(4, $full->summary['total_records']);

        $filtered = $service->analyzeRowsForOwner($import->id, $this->ownerHash('visitante-a'), [
            ['Joao', 'joao@exemplo.com', '10'],
            ['Pedro', 'pedro@exemplo.com', '30'],
        ]);

        $this->assertSame(2, $filtered->totalRecords);
        $this->assertSame(2, $filtered->summary['total_records']);
        $this->assertSame(6, $filtered->summary['filled_cells']);
        $this->assertEqualsWithDelta(40, $filtered->summary['measures'][0]['sum'], 0.0001);

        $cached = $service->getAnalysis($import->id, $this->ownerHash('visitante-a'));
        $this->assertNotNull($cached);
        $this->assertSame(4, $cached->totalRecords);
    }

    private function importCsv(string $csv, string $name): \App\Services\Imports\Data\StoredImport
    {
        $path = sys_get_temp_dir().'/'.uniqid('datacanvas-', true).'-'.$name;
        file_put_contents($path, $csv);
        $file = new UploadedFile($path, $name, 'text/csv', null, true);

        try {
            return app(ImportService::class)->import($file, $this->ownerHash('visitante-a'));
        } finally {
            @unlink($path);
        }
    }

    private function columnNamed(ImportAnalysis $analysis, string $name): ?\App\Services\Analysis\Data\ColumnAnalysis
    {
        foreach ($analysis->columnAnalyses as $column) {
            if ($column->name === $name) {
                return $column;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $domains
     */
    private function domainCount(array $domains, string $domain): int
    {
        foreach ($domains as $item) {
            if (($item['value'] ?? null) === $domain) {
                return (int) ($item['count'] ?? 0);
            }
        }

        return 0;
    }

    private function uniqueCountForNameColumns(ImportAnalysis $analysis): int
    {
        $unique = 0;
        foreach ($analysis->columnAnalyses as $column) {
            if ($column->isName) {
                $unique += $column->distinctCount;
            }
        }

        return $unique;
    }
}
