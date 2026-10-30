<?php

namespace Tests\Feature\Imports;

use App\Services\Imports\ImportRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class ImportCleanupTest extends ImportTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('livewire.temporary_file_upload.disk', 'livewire-testing');
        config()->set('livewire.temporary_file_upload.directory', 'livewire-tmp');
    }

    public function test_cleanup_removes_only_expired_imports(): void
    {
        config()->set('datacanvas.retention_hours', 1);

        Carbon::setTestNow('2026-02-01 08:00:00');
        $expired = $this->importFixture('valid.csv');

        Carbon::setTestNow('2026-02-01 09:30:00');
        config()->set('datacanvas.retention_hours', 24);
        $alive = $this->importFixture('valid.tsv');

        Carbon::setTestNow('2026-02-01 11:00:00');

        $removed = app(ImportRepository::class)->cleanupExpired();

        $this->assertSame(1, $removed);
        $this->assertFalse(Storage::disk('imports')->exists($expired->id.'/metadata.json'));
        $this->assertTrue(Storage::disk('imports')->exists($alive->id.'/metadata.json'));
    }

    public function test_cleanup_removes_orphan_directories(): void
    {
        $disk = Storage::disk('imports');
        $disk->makeDirectory('diretorio-invalido');
        $disk->put('diretorio-invalido/resto.txt', 'parcial');

        $removed = app(ImportRepository::class)->cleanupExpired();

        $this->assertGreaterThanOrEqual(1, $removed);
        $this->assertFalse($disk->exists('diretorio-invalido/resto.txt'));
    }

    public function test_cleanup_command_removes_expired_imports_and_old_temporary_uploads(): void
    {
        config()->set('datacanvas.retention_hours', 1);
        config()->set('datacanvas.livewire_temp_retention_hours', 1);

        Carbon::setTestNow('2026-03-01 10:00:00');
        $import = $this->importFixture('valid.csv');

        $disk = Storage::disk('imports');
        $tempDisk = Storage::disk('livewire-testing');
        $tempDisk->put('livewire-tmp/antigo.csv', 'temporario');
        touch($tempDisk->path('livewire-tmp/antigo.csv'), Carbon::now()->subHours(3)->getTimestamp());

        Carbon::setTestNow('2026-03-01 12:00:00');

        $this->artisan('datacanvas:cleanup-imports')
            ->expectsOutputToContain('Importações removidas: 1')
            ->assertSuccessful();

        $this->assertFalse($disk->exists($import->id.'/metadata.json'));
        $this->assertFalse($tempDisk->exists('livewire-tmp/antigo.csv'));
    }

    public function test_dry_run_does_not_delete_anything(): void
    {
        config()->set('datacanvas.retention_hours', 1);

        Carbon::setTestNow('2026-04-01 10:00:00');
        $import = $this->importFixture('valid.csv');

        Carbon::setTestNow('2026-04-01 12:00:00');

        $this->artisan('datacanvas:cleanup-imports', ['--dry-run' => true])
            ->assertSuccessful();

        $this->assertTrue(Storage::disk('imports')->exists($import->id.'/metadata.json'));
    }
}
