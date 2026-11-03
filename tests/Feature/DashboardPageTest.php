<?php

namespace Tests\Feature;

use Tests\Feature\Imports\ImportTestCase;

class DashboardPageTest extends ImportTestCase
{
    public function test_dashboard_exposes_all_export_formats_and_the_png_trigger(): void
    {
        $token = str_repeat('a', 64);
        $import = $this->importFixture('valid.xlsx', token: $token);

        $response = $this->withSession([
            'datacanvas.visitor_token' => $token,
            'datacanvas.current_import' => $import->id,
        ])->get(route('dashboard', ['importId' => $import->id]));

        $response->assertOk()
            ->assertSee('Exportar')
            ->assertSee('Exportar gráficos (PNG)')
            ->assertSee(route('exports.csv', ['importId' => $import->id]), false)
            ->assertSee(route('exports.json', ['importId' => $import->id]), false)
            ->assertSee(route('exports.pdf', ['importId' => $import->id]), false)
            ->assertSee('data-import-id="'.$import->id.'"', false)
            ->assertSee('name="csrf-token"', false)
            ->assertSee('window.DataCanvasExport', false);
    }
}
