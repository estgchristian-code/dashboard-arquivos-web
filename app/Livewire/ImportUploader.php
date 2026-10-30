<?php

namespace App\Livewire;

use App\Exceptions\ImportException;
use App\Services\Imports\ImportRateLimiter;
use App\Services\Imports\ImportRepository;
use App\Services\Imports\ImportService;
use App\Services\Imports\VisitorIdentity;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

class ImportUploader extends Component
{
    use WithFileUploads;

    private const SESSION_KEY = 'datacanvas.current_import';

    /** @var TemporaryUploadedFile|null */
    public $file = null;

    /**
     * Dados de exibição da importação atual (sem token e sem conteúdo completo).
     *
     * @var array<string, mixed>|null
     */
    public ?array $import = null;

    public string $errorMessage = '';

    public int $uploadKey = 0;

    public function mount(): void
    {
        $id = session(self::SESSION_KEY);

        if (! is_string($id)) {
            return;
        }

        $stored = app(ImportRepository::class)->findOwned($id, app(VisitorIdentity::class)->ownerHash());

        if ($stored === null) {
            session()->forget(self::SESSION_KEY);

            return;
        }

        $this->import = $stored->toViewArray();
    }

    public function updatedFile(): void
    {
        if ($this->file === null) {
            return;
        }

        $this->processUpload();
    }

    public function remove(): void
    {
        $repository = app(ImportRepository::class);
        $identity = app(VisitorIdentity::class);
        $id = session(self::SESSION_KEY);

        if (is_string($id)) {
            $repository->deleteOwned($id, $identity->ownerHash());
        }

        session()->forget(self::SESSION_KEY);

        $this->import = null;
        $this->errorMessage = '';
        $this->uploadKey++;
        $this->resetValidation();
    }

    public function dismissError(): void
    {
        $this->errorMessage = '';
    }

    public function render(): View
    {
        return view('livewire.import-uploader', [
            'limits' => [
                'max_file_label' => $this->formatBytes((int) config('datacanvas.limits.max_file_bytes')),
                'max_rows' => (int) config('datacanvas.limits.max_rows'),
                'max_columns' => (int) config('datacanvas.limits.max_columns'),
                'retention_hours' => (int) config('datacanvas.retention_hours'),
            ],
        ]);
    }

    private function processUpload(): void
    {
        $this->errorMessage = '';
        $this->import = null;
        $this->resetValidation();

        $repository = app(ImportRepository::class);
        $identity = app(VisitorIdentity::class);

        try {
            $ownerHash = $identity->ownerHash();

            app(ImportRateLimiter::class)->ensureAllowed($ownerHash);

            $stored = app(ImportService::class)->import($this->file, $ownerHash);

            $previousId = session(self::SESSION_KEY);

            if (is_string($previousId) && $previousId !== $stored->id) {
                $repository->deleteOwned($previousId, $ownerHash);
            }

            session([self::SESSION_KEY => $stored->id]);
            $this->import = $stored->toViewArray();
        } catch (ImportException $exception) {
            $this->errorMessage = $exception->getMessage();
        } catch (Throwable $exception) {
            // Nunca registrar conteúdo do arquivo. Apenas a classe da falha.
            logger()->error('Falha inesperada ao processar importação.', [
                'exception' => $exception::class,
            ]);

            $this->errorMessage = 'Não foi possível processar o arquivo. Tente novamente.';
        } finally {
            if ($this->file instanceof TemporaryUploadedFile) {
                $this->file->delete();
            }

            $this->file = null;
            $this->uploadKey++;
        }
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return number_format($bytes / (1024 * 1024), 0, ',', '.').' MB';
        }

        return number_format(max(1, (int) ceil($bytes / 1024)), 0, ',', '.').' KB';
    }
}
