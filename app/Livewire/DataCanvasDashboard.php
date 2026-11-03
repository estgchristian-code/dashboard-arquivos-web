<?php

namespace App\Livewire;

use App\Enums\ColumnType;
use App\Services\Analysis\AnalysisService;
use App\Services\Imports\ImportRepository;
use App\Services\Imports\VisitorIdentity;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
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

    public string $search = '';

    public string $sortBy = '';

    public string $sortDirection = 'asc';

    public bool $showTable = false;

    private ?array $rowsCache = null;

    private ?array $filteredRowsCache = null;

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

    public function updated(string $property, mixed $value = null): void
    {
        if ($property === 'search' || str_starts_with($property, 'filters')) {
            $this->tablePage = 1;
        }
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->filters = [];
        $this->tablePage = 1;
    }

    public function toggleTable(): void
    {
        $this->showTable = ! $this->showTable;
    }

    public function sortByColumn(int $index): void
    {
        $key = 'col_'.$index;

        if ($this->sortBy === $key) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $key;
            $this->sortDirection = 'asc';
        }

        $this->tablePage = 1;
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
        $allRows = $this->rows();
        $filteredRows = $this->filteredRows();
        $visibleRecords = count($filteredRows);
        $totalRecords = count($allRows);
        $isFiltered = $this->hasActiveFilters();
        $dashboardAnalysis = $this->analysisForView($filteredRows);

        return view('livewire.data-canvas-dashboard', [
            'tableData' => $this->getTableData($filteredRows, $totalRecords),
            'filterOptions' => $this->buildFilterOptions(),
            'dashboardAnalysis' => $dashboardAnalysis,
            'kpis' => $this->buildKpis($dashboardAnalysis, $visibleRecords, $totalRecords),
            'insights' => $this->buildInsights($dashboardAnalysis),
            'columnInsights' => $this->buildColumnInsights(),
            'viewKey' => $this->viewKey(),
            'isFiltered' => $isFiltered,
            'visibleRecords' => $visibleRecords,
            'totalRecords' => $totalRecords,
        ]);
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function rows(): array
    {
        if ($this->rowsCache !== null) {
            return $this->rowsCache;
        }

        if ($this->import === null) {
            return $this->rowsCache = [];
        }

        try {
            $disk = Storage::disk((string) config('datacanvas.disk', 'imports'));

            if (! $disk->exists($this->importId.'/data.json')) {
                return $this->rowsCache = [];
            }

            $decoded = json_decode($disk->get($this->importId.'/data.json'), true, 512, JSON_THROW_ON_ERROR);
            $rows = is_array($decoded) && is_array($decoded['rows'] ?? null) ? $decoded['rows'] : [];

            $normalized = [];
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $normalized[] = array_map(
                    fn (mixed $value): string => is_scalar($value) ? (string) $value : '',
                    array_values($row),
                );
            }

            return $this->rowsCache = $normalized;
        } catch (\Throwable) {
            return $this->rowsCache = [];
        }
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function filteredRows(): array
    {
        if ($this->filteredRowsCache !== null) {
            return $this->filteredRowsCache;
        }

        $rows = $this->rows();
        $search = mb_strtolower(trim($this->search));
        $activeFilters = [];

        foreach ($this->filters as $key => $value) {
            if (! is_scalar($value)) {
                continue;
            }
            $value = trim((string) $value);
            if ($value !== '') {
                $activeFilters[(string) $key] = $value;
            }
        }

        if ($search === '' && $activeFilters === []) {
            return $this->filteredRowsCache = $rows;
        }

        return $this->filteredRowsCache = array_values(array_filter(
            $rows,
            function (array $row) use ($search, $activeFilters): bool {
                if ($search !== '') {
                    $haystack = mb_strtolower(implode(' ', $row));
                    if (! str_contains($haystack, $search)) {
                        return false;
                    }
                }

                foreach ($activeFilters as $key => $value) {
                    if (str_starts_with($key, 'dom_')) {
                        $index = (int) substr($key, 4);
                        if ($this->emailDomain($row[$index] ?? '') !== mb_strtolower($value)) {
                            return false;
                        }

                        continue;
                    }

                    if (str_starts_with($key, 'col_')) {
                        $index = (int) substr($key, 4);
                        if (mb_strtolower(trim($row[$index] ?? '')) !== mb_strtolower($value)) {
                            return false;
                        }
                    }
                }

                return true;
            },
        ));
    }

    private function hasActiveFilters(): bool
    {
        if (trim($this->search) !== '') {
            return true;
        }

        foreach ($this->filters as $value) {
            if (is_scalar($value) && trim((string) $value) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<int, string>>  $filteredRows
     * @return array<string, mixed>
     */
    private function analysisForView(array $filteredRows): array
    {
        if (! $this->hasActiveFilters()) {
            return $this->analysis ?? [];
        }

        try {
            return app(AnalysisService::class)
                ->analyzeRowsForOwner(
                    $this->importId,
                    app(VisitorIdentity::class)->ownerHash(),
                    $filteredRows,
                )
                ->toArray();
        } catch (\Throwable $exception) {
            logger()->error('Falha ao analisar dados filtrados.', ['exception' => $exception::class]);

            return [
                'import_id' => $this->importId,
                'total_records' => count($filteredRows),
                'column_analyses' => $this->analysis['column_analyses'] ?? [],
                'recommendations' => [],
                'summary' => ['total_records' => count($filteredRows)],
                'issues' => [],
            ];
        }
    }

    private function viewKey(): string
    {
        return substr(md5(json_encode([
            $this->importId,
            $this->search,
            $this->filters,
        ]) ?: ''), 0, 12);
    }

    /**
     * @param  array<int, array<int, string>>  $filteredRows
     * @return array<string, mixed>
     */
    private function getTableData(array $filteredRows, int $totalRecords): array
    {
        if ($this->import === null) {
            return ['rows' => [], 'total' => 0, 'all_total' => 0, 'pages' => 1, 'page' => 1, 'per_page' => $this->tablePerPage];
        }

        $rows = $filteredRows;

        if (preg_match('/^col_(\d+)$/', $this->sortBy, $matches) === 1) {
            $index = (int) $matches[1];
            $direction = $this->sortDirection === 'desc' ? -1 : 1;

            usort($rows, function (array $a, array $b) use ($index, $direction): int {
                $result = strnatcasecmp((string) ($a[$index] ?? ''), (string) ($b[$index] ?? ''));

                return $result * $direction;
            });
        }

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
            'all_total' => $totalRecords,
            'pages' => $pages,
            'page' => $this->tablePage,
            'per_page' => $this->tablePerPage,
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, values: array<int, string>}>
     */
    private function buildFilterOptions(): array
    {
        if ($this->import === null) {
            return [];
        }

        $options = [];
        $analyses = $this->analysis['column_analyses'] ?? [];
        $rows = $this->rows();

        foreach ($this->import['columns'] ?? [] as $index => $column) {
            $analysis = $analyses[$index] ?? null;

            if (! is_array($analysis) || ! empty($analysis['is_identifier'])) {
                continue;
            }

            if (! empty($analysis['is_email'])) {
                $domains = [];
                foreach ($rows as $row) {
                    $domain = $this->emailDomain($row[$index] ?? '');
                    if ($domain !== '') {
                        $domains[$domain] = ($domains[$domain] ?? 0) + 1;
                    }
                }

                if (count($domains) >= 2) {
                    arsort($domains);
                    $options[] = [
                        'key' => 'dom_'.$index,
                        'label' => $column['name'].' · domínio',
                        'values' => array_map('strval', array_keys($domains)),
                    ];
                }

                continue;
            }

            if (! in_array($analysis['type'] ?? '', [ColumnType::Text->value, ColumnType::Boolean->value], true)) {
                continue;
            }

            $values = [];
            foreach ($rows as $row) {
                $value = trim($row[$index] ?? '');
                if ($value === '') {
                    continue;
                }
                $key = mb_strtolower($value);
                $values[$key] ??= ['label' => $value, 'count' => 0];
                $values[$key]['count']++;
            }

            if (count($values) < 2 || count($values) > 50) {
                continue;
            }

            uasort($values, fn (array $a, array $b): int => $b['count'] <=> $a['count']);

            $options[] = [
                'key' => 'col_'.$index,
                'label' => (string) $column['name'],
                'values' => array_values(array_map(fn (array $item): string => $item['label'], $values)),
            ];
        }

        return $options;
    }

    /**
     * @param  array<string, mixed>  $analysis
     * @return array<int, array{label: string, value: string, hint: string, tone: string, icon: string}>
     */
    private function buildKpis(array $analysis, int $visibleRecords, int $totalRecords): array
    {
        $summary = is_array($analysis['summary'] ?? null) ? $analysis['summary'] : [];
        $columnAnalyses = is_array($analysis['column_analyses'] ?? null) ? $analysis['column_analyses'] : [];
        $totalColumns = (int) ($summary['total_columns'] ?? count($this->import['columns'] ?? []));
        $completeness = (float) ($summary['completeness'] ?? 0);
        $emptyCells = (int) ($summary['empty_cells'] ?? 0);

        $kpis = [
            [
                'label' => 'Registros',
                'value' => $this->formatNumber($visibleRecords),
                'hint' => $visibleRecords < $totalRecords
                    ? 'de '.$this->formatNumber($totalRecords).' no arquivo'
                    : 'linhas analisadas',
                'tone' => 'indigo',
                'icon' => 'rows',
            ],
            [
                'label' => 'Colunas',
                'value' => $this->formatNumber($totalColumns),
                'hint' => $this->typeBreakdown($summary),
                'tone' => 'violet',
                'icon' => 'columns',
            ],
            [
                'label' => 'Preenchimento',
                'value' => $this->formatNumber($completeness, 1).'%',
                'hint' => $this->formatNumber($emptyCells).' células vazias',
                'tone' => 'emerald',
                'icon' => 'gauge',
            ],
        ];

        $emailColumns = (int) ($summary['email_columns'] ?? 0);
        $nameColumns = (int) ($summary['name_columns'] ?? 0);

        if ($emailColumns > 0) {
            $kpis[] = [
                'label' => 'Domínios de e-mail',
                'value' => $this->formatNumber(is_array($summary['email_domains'] ?? null) ? count($summary['email_domains']) : 0),
                'hint' => $emailColumns.' coluna'.($emailColumns > 1 ? 's' : '').' de e-mail',
                'tone' => 'sky',
                'icon' => 'mail',
            ];
        } else {
            $categoricalColumns = (int) ($summary['categorical_columns'] ?? 0);
            $kpis[] = [
                'label' => 'Colunas categóricas',
                'value' => $this->formatNumber($categoricalColumns),
                'hint' => 'prontas para segmentar',
                'tone' => 'sky',
                'icon' => 'tag',
            ];
        }

        if ($nameColumns > 0) {
            $uniqueNames = 0;
            foreach ($columnAnalyses as $columnAnalysis) {
                if (! empty($columnAnalysis['is_name'])) {
                    $uniqueNames += (int) ($columnAnalysis['distinct_count'] ?? 0);
                }
            }

            $kpis[] = [
                'label' => 'Nomes únicos',
                'value' => $this->formatNumber($uniqueNames),
                'hint' => 'em '.$nameColumns.' coluna'.($nameColumns > 1 ? 's' : '').' de nome',
                'tone' => 'cyan',
                'icon' => 'users',
            ];
        }

        $measures = is_array($summary['measures'] ?? null) ? $summary['measures'] : [];
        if (! empty($measures)) {
            $measure = $measures[0];
            $kpis[] = [
                'label' => 'Soma · '.($measure['name'] ?? 'medida'),
                'value' => $this->formatNumber($measure['sum'] ?? 0),
                'hint' => 'média '.$this->formatNumber($measure['mean'] ?? 0),
                'tone' => 'amber',
                'icon' => 'sum',
            ];
        }

        if (is_array($summary['date_range'] ?? null) && ! empty($summary['date_range']['min'])) {
            $period = $summary['date_range'];
            $kpis[] = [
                'label' => 'Período',
                'value' => $this->formatDate((string) $period['min']).' → '.$this->formatDate((string) $period['max']),
                'hint' => 'intervalo identificado',
                'tone' => 'rose',
                'icon' => 'calendar',
            ];
        }

        return array_slice($kpis, 0, 6);
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function typeBreakdown(array $summary): string
    {
        $parts = [];

        foreach ([
            'numeric_columns' => ['numérica', 'numéricas'],
            'text_columns' => ['texto', 'texto'],
            'date_columns' => ['data', 'datas'],
        ] as $key => [$singular, $plural]) {
            $count = (int) ($summary[$key] ?? 0);
            if ($count > 0) {
                $parts[] = $count.' '.($count === 1 ? $singular : $plural);
            }
        }

        return $parts === [] ? 'estrutura identificada' : implode(' · ', $parts);
    }

    /**
     * @param  array<string, mixed>  $analysis
     * @return array<int, string>
     */
    private function buildInsights(array $analysis): array
    {
        $summary = is_array($analysis['summary'] ?? null) ? $analysis['summary'] : [];
        $columns = is_array($analysis['column_analyses'] ?? null) ? $analysis['column_analyses'] : [];
        $insights = [];

        $totalRecords = (int) ($summary['total_records'] ?? 0);
        $totalColumns = (int) ($summary['total_columns'] ?? 0);
        $completeness = (float) ($summary['completeness'] ?? 0);

        if ($totalRecords > 0) {
            $insights[] = 'A base tem '.$this->formatNumber($totalRecords).' registros em '.$this->formatNumber($totalColumns).' colunas, com '.$this->formatNumber($completeness, 1).'% das células preenchidas.';
        }

        $emailColumn = null;
        $nameColumn = null;
        $measure = is_array($summary['measures'] ?? null) ? ($summary['measures'][0] ?? null) : null;

        foreach ($columns as $column) {
            if ($emailColumn === null && ! empty($column['is_email'])) {
                $emailColumn = $column;
            }
            if ($nameColumn === null && ! empty($column['is_name'])) {
                $nameColumn = $column;
            }
        }

        if (is_array($emailColumn)) {
            $domains = is_array($emailColumn['email_domains'] ?? null) ? $emailColumn['email_domains'] : [];
            $topDomain = $domains[0] ?? null;

            if (is_array($topDomain)) {
                $insights[] = 'Em "'.$emailColumn['name'].'", o domínio mais frequente é '.$topDomain['value'].' ('.$this->formatNumber($topDomain['count'] ?? 0).' registros), entre '.$this->formatNumber(count($domains)).' domínios identificados.';
            }
        }

        if (is_array($nameColumn)) {
            $unique = (int) ($nameColumn['distinct_count'] ?? 0);
            $duplicates = (int) ($nameColumn['duplicate_count'] ?? 0);

            $insights[] = $duplicates > 0
                ? 'Há '.$this->formatNumber($unique).' nomes únicos em "'.$nameColumn['name'].'"; '.$this->formatNumber($duplicates).' registros repetem nomes já existentes.'
                : 'Todos os '.$this->formatNumber($unique).' nomes de "'.$nameColumn['name'].'" são únicos.';
        }

        if (is_array($measure) && isset($measure['name'])) {
            $insights[] = 'A soma de "'.$measure['name'].'" é '.$this->formatNumber($measure['sum'] ?? 0).', com média de '.$this->formatNumber($measure['mean'] ?? 0).' por registro.';
        }

        if (is_array($summary['date_range'] ?? null) && ! empty($summary['date_range']['min'])) {
            $insights[] = 'O período identificado vai de '.$this->formatDate((string) $summary['date_range']['min']).' a '.$this->formatDate((string) $summary['date_range']['max']).'.';
        }

        foreach ($columns as $column) {
            if (! empty($column['is_email']) || ! empty($column['is_name']) || ! empty($column['is_identifier'])) {
                continue;
            }

            $categories = is_array($column['categories'] ?? null) ? $column['categories'] : [];
            $top = $categories[0] ?? null;

            if (is_array($top) && isset($top['value'], $top['count'])) {
                $insights[] = 'A categoria mais frequente em "'.$column['name'].'" é "'.$top['value'].'" ('.$this->formatNumber($top['count']).' registros).';
                break;
            }
        }

        $emptiest = null;
        foreach ($columns as $column) {
            $empty = (int) ($column['empty_count'] ?? 0);
            if ($empty > 0 && ($emptiest === null || $empty > $emptiest['empty_count'])) {
                $emptiest = ['name' => $column['name'], 'empty_count' => $empty];
            }
        }

        if (is_array($emptiest)) {
            $insights[] = 'A coluna "'.$emptiest['name'].'" concentra '.$this->formatNumber($emptiest['empty_count']).' células vazias.';
        }

        return array_slice($insights, 0, 5);
    }

    /**
     * @return array<int, array{name: string, type_label: string, empty_count: int, distinct_count: int, flags: array<int, string>}>
     */
    private function buildColumnInsights(): array
    {
        if ($this->import === null) {
            return [];
        }

        $analyses = $this->analysis['column_analyses'] ?? [];
        $columns = [];

        foreach ($this->import['columns'] ?? [] as $index => $column) {
            $analysis = is_array($analyses[$index] ?? null) ? $analyses[$index] : [];
            $flags = [];

            if (! empty($analysis['is_identifier'])) {
                $flags[] = 'Identificador';
            }
            if (! empty($analysis['is_email'])) {
                $flags[] = 'E-mail';
            }
            if (! empty($analysis['is_name'])) {
                $flags[] = 'Nome';
            }
            if (! empty($analysis['has_temporal_trend'])) {
                $flags[] = 'Data';
            }

            $columns[] = [
                'name' => (string) $column['name'],
                'type_label' => (string) ($column['type_label'] ?? ''),
                'empty_count' => (int) ($analysis['empty_count'] ?? $column['empty_count'] ?? 0),
                'distinct_count' => (int) ($analysis['distinct_count'] ?? 0),
                'flags' => $flags,
            ];
        }

        return $columns;
    }

    private function emailDomain(string $value): string
    {
        $candidate = trim($value);

        if (! filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
            return '';
        }

        $at = strrpos($candidate, '@');

        return $at === false ? '' : mb_strtolower(substr($candidate, $at + 1));
    }

    private function formatNumber(mixed $value, int $decimals = 0): string
    {
        if (! is_numeric($value)) {
            return (string) $value;
        }

        $number = (float) $value;

        if ($decimals === 0 && floor($number) !== $number) {
            $decimals = 2;
        }

        return number_format($number, $decimals, ',', '.');
    }

    private function formatDate(string $date): string
    {
        try {
            return Carbon::createFromFormat('Y-m-d', $date)->format('d/m/Y');
        } catch (\Throwable) {
            return $date;
        }
    }
}
