<?php

namespace Tests\Feature\Imports;

use App\Exceptions\ImportException;
use App\Services\Imports\ImportRateLimiter;

class ImportValidationTest extends ImportTestCase
{
    public function test_empty_file_is_rejected(): void
    {
        $this->expectExceptionObject(ImportException::emptyFile());

        $this->importFixture('empty.csv');
    }

    public function test_whitespace_only_file_is_rejected(): void
    {
        $this->expectExceptionObject(ImportException::emptyFile());

        $this->importFixture('whitespace.csv');
    }

    public function test_binary_content_disguised_as_csv_is_rejected(): void
    {
        $this->expectExceptionObject(ImportException::binaryContent());

        $this->importFixture('fake-image.csv');
    }

    public function test_xlsx_content_with_csv_extension_is_rejected(): void
    {
        $this->expectExceptionObject(ImportException::formatMismatch());

        $this->importFixture('mismatch.csv');
    }

    public function test_csv_content_with_xlsx_extension_is_rejected(): void
    {
        $this->expectExceptionObject(ImportException::formatMismatch());

        $this->importFixture('mismatch.xlsx');
    }

    public function test_corrupt_xlsx_is_rejected(): void
    {
        $this->expectExceptionObject(ImportException::corruptSpreadsheet());

        $this->importFixture('corrupt.xlsx');
    }

    public function test_corrupt_xls_is_rejected(): void
    {
        $this->expectExceptionObject(ImportException::corruptSpreadsheet());

        $this->importFixture('corrupt.xls');
    }

    public function test_corrupt_zip_disguised_as_csv_is_rejected(): void
    {
        $this->expectExceptionObject(ImportException::formatMismatch());

        $this->importFixture('corrupt.csv');
    }

    public function test_unsupported_extension_is_rejected_even_with_valid_content(): void
    {
        $this->expectExceptionObject(ImportException::unsupportedExtension());

        $this->importFixture('valid.csv', 'planilha.txt');
    }

    public function test_file_without_extension_is_rejected(): void
    {
        $this->expectExceptionObject(ImportException::unsupportedExtension());

        $this->importFixture('valid.csv', 'planilha');
    }

    public function test_size_limit_is_enforced(): void
    {
        config()->set('datacanvas.limits.max_file_bytes', 100);

        $this->expectExceptionObject(ImportException::tooLarge(100));

        $this->importFixture('valid.csv');
    }

    public function test_row_limit_is_enforced(): void
    {
        config()->set('datacanvas.limits.max_rows', 2);

        $this->expectExceptionObject(ImportException::tooManyRows(2));

        $this->importFixture('valid.csv');
    }

    public function test_column_limit_is_enforced(): void
    {
        config()->set('datacanvas.limits.max_columns', 3);

        $this->expectExceptionObject(ImportException::tooManyColumns(3));

        $this->importFixture('valid.csv');
    }

    public function test_cell_limit_is_enforced(): void
    {
        config()->set('datacanvas.limits.max_cells', 10);

        $this->expectExceptionObject(ImportException::tooManyCells(10));

        $this->importFixture('valid.csv');
    }

    public function test_invalid_json_is_rejected(): void
    {
        $this->expectExceptionObject(ImportException::invalidJson());

        $this->importFixture('invalid.json');
    }

    public function test_non_tabular_json_is_rejected(): void
    {
        $this->expectExceptionObject(ImportException::nonTabularJson());

        $this->importFixture('non-tabular.json');
    }

    public function test_nested_json_values_are_rejected(): void
    {
        $this->expectExceptionObject(ImportException::nestedJson());

        $this->importFixture('nested.json');
    }

    public function test_zip_with_path_traversal_entry_is_rejected(): void
    {
        $this->expectExceptionObject(ImportException::unsafeArchive());

        $this->importFixture('unsafe-path.xlsx');
    }

    public function test_zip_bomb_is_rejected_by_compression_ratio(): void
    {
        $this->expectExceptionObject(ImportException::unsafeArchive());

        $this->importFixture('zip-bomb.xlsx');
    }

    public function test_macro_enabled_workbook_is_rejected(): void
    {
        $this->expectExceptionObject(ImportException::embeddedContent());

        $this->importFixture('macro.xlsx');
    }

    public function test_workbook_with_external_entity_declaration_is_rejected(): void
    {
        $this->expectExceptionObject(ImportException::unsafeArchive());

        $this->importFixture('dtd.xlsx');
    }

    public function test_browser_mime_type_is_not_trusted_when_content_is_valid(): void
    {
        $import = $this->importFixture('valid.csv', 'valid.csv', 'application/zip');

        $this->assertSame(4, $import->rowCount);
        $this->assertSame(7, $import->columnCount);
    }

    public function test_script_content_is_kept_as_data_and_never_evaluated(): void
    {
        $import = $this->importFixture('xss.csv');

        $this->assertSame(2, $import->rowCount);
        $this->assertSame("<script>alert('x')</script>", $import->previewRows[0][1]);
        $this->assertSame('<img src=x onerror=alert(1)>', $import->previewRows[1][1]);
    }

    public function test_rates_are_limited_per_visitor_when_enabled(): void
    {
        config()->set('datacanvas.rate_limit.enabled', true);
        config()->set('datacanvas.rate_limit.per_minute', 2);
        config()->set('datacanvas.rate_limit.per_hour', 100);

        $limiter = app(ImportRateLimiter::class);
        $limiter->ensureAllowed($this->ownerHash());
        $limiter->ensureAllowed($this->ownerHash());

        $this->expectException(ImportException::class);
        $this->expectExceptionMessage('Muitas tentativas de importação');

        $limiter->ensureAllowed($this->ownerHash());
    }
}
