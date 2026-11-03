<?php

namespace Tests\Feature\Dashboard;

use App\Livewire\DataCanvasDashboard;
use App\Services\Imports\Data\StoredImport;
use App\Services\Imports\ImportService;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\Feature\Imports\ImportTestCase;

class DataCanvasDashboardTest extends ImportTestCase
{
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->token = str_repeat('d', 64);
    }

    public function test_dashboard_renders_kpis_charts_and_secondary_table(): void
    {
        $import = $this->importFixture('valid.csv', token: $this->token);

        $component = $this->dashboard($import);

        $component
            ->assertOk()
            ->assertSet('errorMessage', '')
            ->assertSet('showTable', false)
            ->assertSee('Registros')
            ->assertSee('Colunas')
            ->assertSee('Preenchimento')
            ->assertSee('Painel visual')
            ->assertSee('Registros completos')
            ->assertDontSee('Anterior')
            ->assertViewHas('totalRecords', 4)
            ->assertViewHas('visibleRecords', 4)
            ->assertViewHas('kpis', fn (array $kpis): bool => $kpis[0]['value'] === '4')
            ->assertViewHas('dashboardAnalysis', fn (array $analysis): bool => ! empty($analysis['recommendations']));
    }

    public function test_category_filter_recalculates_visible_records_and_charts(): void
    {
        $import = $this->importFixture('valid.csv', token: $this->token);

        $component = $this->dashboard($import)
            ->set('filters.col_1', 'Sul')
            ->assertSet('tablePage', 1)
            ->assertViewHas('visibleRecords', 2)
            ->assertViewHas('isFiltered', true)
            ->assertViewHas('totalRecords', 4)
            ->assertViewHas('kpis', fn (array $kpis): bool => $kpis[0]['value'] === '2')
            ->assertViewHas('dashboardAnalysis', function (array $analysis): bool {
                return $analysis['total_records'] === 2;
            })
            ->assertSee('Limpar filtros');

        $component->call('clearFilters')
            ->assertSet('search', '')
            ->assertSet('filters', [])
            ->assertViewHas('visibleRecords', 4)
            ->assertViewHas('isFiltered', false);
    }

    public function test_search_filters_records_across_columns(): void
    {
        $import = $this->importFixture('valid.csv', token: $this->token);

        $this->dashboard($import)
            ->set('search', 'Caderno')
            ->assertViewHas('visibleRecords', 1)
            ->assertViewHas('isFiltered', true)
            ->assertViewHas('tableData', fn (array $table): bool => $table['rows'][0][0] === 'Caderno');
    }

    public function test_email_domain_filter_is_available_and_recalculates_dashboard(): void
    {
        $import = $this->importCsv(
            "Nome,Email\nJoao Silva,joao@exemplo.com\nMaria Santos,maria@gmail.com\nPedro Costa,pedro@exemplo.com\nAna Lima,ana@gmail.com\n",
            'contatos.csv',
        );

        $component = $this->dashboard($import)
            ->assertSee('Domínios de e-mail')
            ->assertSee('Nomes únicos')
            ->assertViewHas('filterOptions', function (array $options): bool {
                foreach ($options as $option) {
                    if ($option['key'] === 'dom_1') {
                        return $option['values'] === ['exemplo.com', 'gmail.com']
                            || $option['values'] === ['gmail.com', 'exemplo.com'];
                    }
                }

                return false;
            })
            ->set('filters.dom_1', 'gmail.com')
            ->assertViewHas('visibleRecords', 2)
            ->assertViewHas('dashboardAnalysis', function (array $analysis): bool {
                return $analysis['total_records'] === 2
                    && $analysis['summary']['email_columns'] === 1
                    && count($analysis['summary']['email_domains']) === 1
                    && $analysis['summary']['email_domains'][0]['value'] === 'gmail.com';
            });
    }

    public function test_text_only_spreadsheet_does_not_invent_numeric_kpis(): void
    {
        $import = $this->importCsv(
            "Nome,Email\nJoao Silva,joao@exemplo.com\nMaria Santos,maria@gmail.com\n",
            'apenas-texto.csv',
        );

        $this->dashboard($import)
            ->assertDontSee('Soma ·')
            ->assertDontSee('Período')
            ->assertSee('Nomes únicos');

        $component = $this->dashboard($import);
        $component->assertViewHas('kpis', function (array $kpis): bool {
            foreach ($kpis as $kpi) {
                if (str_starts_with($kpi['label'], 'Soma ·') || $kpi['label'] === 'Período') {
                    return false;
                }
            }

            return true;
        });
    }

    public function test_secondary_table_toggles_sorts_and_paginates(): void
    {
        $import = $this->importFixture('valid.csv', token: $this->token);

        $component = $this->dashboard($import)
            ->set('tablePerPage', 2)
            ->call('toggleTable')
            ->assertSet('showTable', true)
            ->assertSee('Anterior')
            ->assertSee('Próxima')
            ->assertViewHas('tableData', fn (array $table): bool => $table['pages'] === 2 && $table['total'] === 4);

        $component->call('sortByColumn', 2)
            ->assertSet('sortBy', 'col_2')
            ->assertSet('sortDirection', 'asc')
            ->assertViewHas('tableData', fn (array $table): bool => $table['rows'][0][2] === '0');

        $component->call('sortByColumn', 2)
            ->assertSet('sortDirection', 'desc')
            ->assertViewHas('tableData', fn (array $table): bool => $table['rows'][0][2] === '10');

        $component->call('nextPage')
            ->assertSet('tablePage', 2)
            ->assertViewHas('tableData', fn (array $table): bool => $table['page'] === 2);

        $component->call('prevPage')
            ->assertSet('tablePage', 1);
    }

    public function test_unknown_import_shows_friendly_error(): void
    {
        $this->withSession(['datacanvas.visitor_token' => $this->token]);

        Livewire::test(DataCanvasDashboard::class, ['importId' => str_repeat('0', 32)])
            ->assertOk()
            ->assertSet('loading', false)
            ->assertSee('Importação não encontrada ou expirada.');
    }

    private function dashboard(StoredImport $import): \Livewire\Features\SupportTesting\Testable
    {
        $this->withSession([
            'datacanvas.visitor_token' => $this->token,
            'datacanvas.current_import' => $import->id,
        ]);

        return Livewire::test(DataCanvasDashboard::class, ['importId' => $import->id]);
    }

    private function importCsv(string $csv, string $name): StoredImport
    {
        $path = sys_get_temp_dir().'/'.uniqid('datacanvas-dashboard-', true).'-'.$name;
        file_put_contents($path, $csv);
        $file = new UploadedFile($path, $name, 'text/csv', null, true);

        try {
            return app(ImportService::class)->import($file, hash('sha256', $this->token));
        } finally {
            @unlink($path);
        }
    }
}
