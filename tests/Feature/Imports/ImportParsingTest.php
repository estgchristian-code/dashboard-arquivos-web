<?php

namespace Tests\Feature\Imports;

use App\Enums\ImportFormat;
use Illuminate\Support\Facades\Storage;

class ImportParsingTest extends ImportTestCase
{
    public function test_csv_import_detects_headers_delimiter_encoding_types_and_empty_cells(): void
    {
        $import = $this->importFixture('valid.csv');

        $this->assertSame(ImportFormat::Csv, $import->format);
        $this->assertSame(4, $import->rowCount);
        $this->assertSame(7, $import->columnCount);
        $this->assertSame(';', $import->delimiter);
        $this->assertSame('UTF-8', $import->encoding);
        $this->assertSame(2, $import->emptyCells);
        $this->assertSame(
            ['Produto', 'Região', 'Quantidade', 'Preço', 'Data', 'Ativo', 'Observação'],
            array_column($import->columns, 'name'),
        );
        $this->assertSame('integer', $import->columns[2]['type']);
        $this->assertSame('decimal', $import->columns[3]['type']);
        $this->assertSame('date', $import->columns[4]['type']);
        $this->assertSame('boolean', $import->columns[5]['type']);
        $this->assertSame(2, $import->columns[6]['empty_count']);
        $this->assertCount(4, $import->previewRows);
        $this->assertSame(['Caneta', 'Sul', '10', '2,50', '01/02/2026', 'Sim', ''], $import->previewRows[0]);
    }

    public function test_tsv_import_detects_tab_delimiter(): void
    {
        $import = $this->importFixture('valid.tsv');

        $this->assertSame(ImportFormat::Tsv, $import->format);
        $this->assertSame("\t", $import->delimiter);
        $this->assertSame(3, $import->rowCount);
        $this->assertSame(['Nome', 'Cidade', 'Total'], array_column($import->columns, 'name'));
        $this->assertSame(['Ana', 'Curitiba', '1200'], $import->previewRows[0]);
    }

    public function test_xlsx_import_reads_first_sheet_and_detects_types(): void
    {
        $import = $this->importFixture('valid.xlsx');

        $this->assertSame(ImportFormat::Xlsx, $import->format);
        $this->assertSame('Vendas', $import->sheetName);
        $this->assertSame(4, $import->rowCount);
        $this->assertSame(7, $import->columnCount);
        $this->assertSame(['Produto', 'Região', 'Quantidade', 'Preço', 'Data', 'Ativo', 'Observação'], array_column($import->columns, 'name'));
        $this->assertSame('integer', $import->columns[2]['type']);
        $this->assertSame('date', $import->columns[4]['type']);
        $this->assertSame('Caderno', $import->previewRows[1][0]);
    }

    public function test_xls_import_reads_legacy_excel_file(): void
    {
        $import = $this->importFixture('valid.xls');

        $this->assertSame(ImportFormat::Xls, $import->format);
        $this->assertSame('Vendas', $import->sheetName);
        $this->assertSame(4, $import->rowCount);
        $this->assertSame(7, $import->columnCount);
    }

    public function test_tabular_json_with_objects_is_imported(): void
    {
        $import = $this->importFixture('valid.json');

        $this->assertSame(ImportFormat::Json, $import->format);
        $this->assertSame(3, $import->rowCount);
        $this->assertSame(['Produto', 'Quantidade', 'Preço', 'Ativo', 'Data'], array_column($import->columns, 'name'));
        $this->assertSame('integer', $import->columns[1]['type']);
        $this->assertSame('boolean', $import->columns[3]['type']);
        $this->assertSame(['Caneta', '10', '2.5', 'true', '01/02/2026'], $import->previewRows[0]);
    }

    public function test_tabular_json_with_lists_is_imported(): void
    {
        $import = $this->importFixture('valid-list.json');

        $this->assertSame(ImportFormat::Json, $import->format);
        $this->assertSame(2, $import->rowCount);
        $this->assertSame(['Produto', 'Quantidade'], array_column($import->columns, 'name'));
        $this->assertSame(['Caderno', '5'], $import->previewRows[1]);
    }

    public function test_windows_1252_csv_is_converted_to_utf8(): void
    {
        $import = $this->importFixture('latin1.csv');

        $this->assertSame('Windows-1252', $import->encoding);
        $this->assertSame('João', $import->previewRows[0][0]);
        $this->assertSame('São Paulo', $import->previewRows[0][1]);
        $this->assertSame('Ação', $import->previewRows[0][2]);
    }

    public function test_headerless_csv_receives_generated_column_names(): void
    {
        $import = $this->importFixture('headerless.csv');

        $this->assertSame(['Coluna 1', 'Coluna 2', 'Coluna 3'], array_column($import->columns, 'name'));
        $this->assertSame(3, $import->rowCount);
    }

    public function test_preview_is_limited_but_full_row_count_is_reported(): void
    {
        config()->set('datacanvas.preview_rows', 2);

        $import = $this->importFixture('valid.csv');

        $this->assertSame(4, $import->rowCount);
        $this->assertCount(2, $import->previewRows);
        $this->assertTrue($import->toViewArray()['has_more']);
    }

    public function test_import_is_stored_privately_with_random_names_and_owner_hash(): void
    {
        $ownerHash = $this->ownerHash('visitante-a');
        $import = $this->importFixture('valid.csv', 'relatório confidencial.csv');

        $disk = Storage::disk('imports');
        $files = $disk->allFiles($import->id);

        $this->assertContains($import->id.'/metadata.json', $files);
        $this->assertContains($import->id.'/data.json', $files);
        $this->assertContains($import->id.'/preview.json', $files);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $import->id);
        $this->assertCount(4, $files);

        $joined = implode('|', $files);
        $this->assertStringNotContainsString('relatório confidencial', $joined);
        $this->assertStringNotContainsString('valid.csv', $joined);

        $sourceFile = collect($files)->first(fn (string $file): bool => str_ends_with($file, '.csv'));
        $this->assertNotNull($sourceFile);
        $this->assertMatchesRegularExpression('#'.$import->id.'/[a-f0-9]{32}\.csv$#', $sourceFile);

        $metadata = json_decode($disk->get($import->id.'/metadata.json'), true);
        $this->assertSame($ownerHash, $metadata['owner_hash']);
        $this->assertStringNotContainsString('visitante-a', $disk->get($import->id.'/metadata.json'));

        $this->assertFalse((bool) config('filesystems.disks.imports.serve'));
        $this->assertStringNotContainsString(
            DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR,
            (string) config('filesystems.disks.imports.root'),
        );
    }

    public function test_full_normalized_rows_are_persisted_for_the_next_stage(): void
    {
        $import = $this->importFixture('valid.csv');

        $data = json_decode(Storage::disk('imports')->get($import->id.'/data.json'), true);

        $this->assertCount(4, $data['rows']);
        $this->assertSame('Borracha', $data['rows'][3][0]);
    }
}
