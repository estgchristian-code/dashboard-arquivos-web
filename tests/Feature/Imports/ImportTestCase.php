<?php

namespace Tests\Feature\Imports;

use App\Services\Imports\Data\StoredImport;
use App\Services\Imports\ImportService;
use Illuminate\Http\Testing\File as TestingFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

abstract class ImportTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('imports');
        Storage::fake('livewire-testing');

        config()->set([
            'datacanvas.disk' => 'imports',
            'datacanvas.retention_hours' => 24,
            'datacanvas.preview_rows' => 25,
            'datacanvas.limits.max_file_bytes' => 10 * 1024 * 1024,
            'datacanvas.limits.max_rows' => 10000,
            'datacanvas.limits.max_columns' => 100,
            'datacanvas.limits.max_cells' => 200000,
            'datacanvas.limits.max_processing_seconds' => 20,
            'datacanvas.rate_limit.enabled' => false,
            'livewire.temporary_file_upload.disk' => 'livewire-testing',
            'livewire.temporary_file_upload.directory' => 'livewire-tmp',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    protected function fixturePath(string $name): string
    {
        $path = base_path('tests/Fixtures/imports/'.$name);

        $this->assertFileExists($path, 'Fixture real não encontrada: '.$name);

        return $path;
    }

    protected function upload(string $fixture, ?string $name = null, ?string $mime = null): UploadedFile
    {
        return new UploadedFile(
            $this->fixturePath($fixture),
            $name ?? $fixture,
            $mime,
            null,
            true,
        );
    }

    /**
     * Arquivo real de fixture empacotado como o Livewire espera nos testes,
     * sem gerar conteúdo sintético.
     */
    protected function livewireUpload(string $fixture, ?string $name = null): TestingFile
    {
        $stream = fopen($this->fixturePath($fixture), 'rb');

        $this->assertIsResource($stream);

        return new TestingFile($name ?? $fixture, $stream);
    }

    protected function ownerHash(string $token = 'visitante-a'): string
    {
        return hash('sha256', $token);
    }

    protected function importFixture(string $fixture, ?string $name = null, ?string $mime = null, string $token = 'visitante-a'): StoredImport
    {
        return app(ImportService::class)->import(
            $this->upload($fixture, $name, $mime),
            $this->ownerHash($token),
        );
    }
}
