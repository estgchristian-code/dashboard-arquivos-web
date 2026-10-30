<?php

namespace App\Livewire;

use App\Services\Analysis\AnalysisService;
use App\Services\Imports\ImportRepository;
use App\Services\Imports\VisitorIdentity;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

class DataCanvasDashboard extends Component
{
    private const SESSION_KEY = 'datacanvas.current_import';

    public string $importId = '';

    public ?array $import = null;

    public ?array $analysis = null;

    public bool $loading = true;

    public string $errorMessage = '';

    public int $tablePage = 1;

    public int $tablePerPage = 25;

    public array $filters = [];

    public function mount(?string $importId = null): void
    {
        if ($importId === null) {
            $importId = session(self::SESSION_KEY);
        }

        if (! is_string($importId)) {
            $this->loading = false;
            $this->errorMessage = 'Nenhuma importação encontrada.';

            return;
        }

        $this->importId = $importId;
        $this->loadDashboard();
    }

    public function loadDashboard(): void
    {
        $this->loading = true;
        $this->errorMessage = '';

        try {
            $identity = app(VisitorIdentity::class);
            $ownerHash = $identity->ownerHash();

            $repository = app(ImportRepository::class);
            $stored = $repository->findOwned($this->importId, $ownerHash);

            if ($stored === null) {
                session()->forget(self::SESSION_KEY);
                $this->errorMessage = 'Importação não encontrada ou expirada.';
                $this->loading = false;

                return;
            }

            $this->import = $stored->toViewArray();

            $analysisService = app(AnalysisService::class);
            $analysis = $analysisService->analyzeForOwner($this->importId, $ownerHash);

            if ($analysis === null) {
                $this->errorMessage = 'Não foi possível gerar a análise dos dados.';
                $this->loading = false;

                return;
            }

            $this->analysis = $analysis->toArray();
        } catch (\Throwable $e) {
            logger()->error('Falha ao carregar dashboard.', ['exception' => $e::class]);
            $this->errorMessage = 'Erro ao carregar o dashboard.';
        } finally {
            $this->loading = false;
        }
    }

    public function goToImport(): void
    {
        $this->redirectRoute('home');
    }

    public function importAnother(): void
    {
        app(ImportRepository::class)->deleteOwned($this->importId, app(VisitorIdentity::class)->ownerHash());
        session()->forget(self::SESSION_KEY);
        $this->redirectRoute('home');
    }

    public function nextPage(): void
    {
        $this->tablePage++;
    }

    public function prevPage(): void
    {
        if ($this->tablePage > 1) {
            $this->tablePage--;
        }
    }

    public function goToPage(int $page): void
    {
        if ($page >= 1) {
            $this->tablePage = $page;
        }
    }

    public function render(): View
    {
        $tableData = $this->getTableData();

        return view('livewire.data-canvas-dashboard', [
            'tableData' => $tableData,
        ]);
    }

    private function getTableData(): array
    {
        if ($this->import === null) {
            return ['rows' => [], 'total' => 0, 'pages' => 1, 'page' => 1];
        }

        try {
            $disk = Storage::disk((string) config('datacanvas.disk', 'imports'));
            if (! $disk->exists($this->importId.'/data.json')) {
                return ['rows' => [], 'total' => $this->import['row_count'] ?? 0, 'pages' => 1, 'page' => 1];
            }
            $contents = $disk->get($this->importId.'/data.json');
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            $rows = is_array($decoded) && is_array($decoded['rows'] ?? null) ? $decoded['rows'] : [];
            $total = count($rows);
            $pages = max(1, (int) ceil($total / $this->tablePerPage));
            if ($this->tablePage > $pages) {
                $this->tablePage = $pages;
            }
            $offset = ($this->tablePage - 1) * $this->tablePerPage;
            $paged = array_slice($rows, $offset, $this->tablePerPage);

            return [
                'rows' => $paged,
                'total' => $total,
                'pages' => $pages,
                'page' => $this->tablePage,
                'per_page' => $this->tablePerPage,
            ];
        } catch (\Throwable) {
            return ['rows' => [], 'total' => $this->import['row_count'] ?? 0, 'pages' => 1, 'page' => 1];
        }
    }
}
