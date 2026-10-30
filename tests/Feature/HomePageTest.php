<?php

namespace Tests\Feature;

use Tests\TestCase;

class HomePageTest extends TestCase
{
    public function test_home_page_renders_the_datacanvas_landing_page(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('DataCanvas')
            ->assertSee('Transforme planilhas em')
            ->assertSee('Enviar planilha')
            ->assertSeeLivewire('import-uploader');
    }

    public function test_home_page_presents_the_real_import_area(): void
    {
        $response = $this->get('/');

        $response->assertSee('Arraste e solte sua planilha aqui')
            ->assertSee('Selecionar arquivo')
            ->assertSee('CSV, TSV, XLSX, XLS ou JSON tabular')
            ->assertDontSee('Nenhum arquivo é processado nesta versão.');
    }
}
