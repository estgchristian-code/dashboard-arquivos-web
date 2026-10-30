<?php

namespace App\Console\Commands;

use App\Services\Imports\ImportRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CleanupImports extends Command
{
    protected $signature = 'datacanvas:cleanup-imports {--dry-run : Apenas informa o que seria removido}';

    protected $description = 'Remove importações temporárias expiradas e uploads temporários antigos';

    public function handle(ImportRepository $repository): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->info('Simulação: nenhum arquivo será removido.');

            return self::SUCCESS;
        }

        $imports = $repository->cleanupExpired();
        $temporaryUploads = $this->cleanupTemporaryUploads();

        $this->info("Importações removidas: {$imports}");
        $this->info("Uploads temporários removidos: {$temporaryUploads}");

        return self::SUCCESS;
    }

    private function cleanupTemporaryUploads(): int
    {
        $diskName = (string) (config('livewire.temporary_file_upload.disk') ?: config('filesystems.default'));
        $directory = trim((string) (config('livewire.temporary_file_upload.directory') ?: 'livewire-tmp'), '/');
        $hours = max(1, (int) config('datacanvas.livewire_temp_retention_hours', 24));
        $threshold = Carbon::now()->subHours($hours)->getTimestamp();

        try {
            $disk = Storage::disk($diskName);
            $files = $disk->files($directory);
        } catch (Throwable) {
            return 0;
        }

        $removed = 0;

        foreach ($files as $file) {
            try {
                if ($disk->lastModified($file) > $threshold) {
                    continue;
                }

                if ($disk->delete($file)) {
                    $removed++;
                }
            } catch (Throwable) {
                continue;
            }
        }

        return $removed;
    }
}
