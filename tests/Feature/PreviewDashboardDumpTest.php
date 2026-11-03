<?php

namespace Tests\Feature;

use App\Services\Imports\ImportService;
use Illuminate\Http\UploadedFile;
use Tests\Feature\Imports\ImportTestCase;

class PreviewDashboardDumpTest extends ImportTestCase
{
    public function test_dump_previews(): void
    {
        $token = str_repeat('e', 64);

        $contacts = "Nome,Email,Departamento,Cidade\n"
            ."Joao Silva,joao.silva@crcpr.org.br,Contabilidade,Curitiba\n"
            ."Maria Santos,maria.santos@gmail.com,Fiscalizacao,Londrina\n"
            ."Pedro Costa,pedro.costa@crcpr.org.br,Contabilidade,Maringa\n"
            ."Ana Lima,ana.lima@gmail.com,Registro,Cascavel\n"
            ."Joao Silva,joao.silva@crcpr.org.br,Contabilidade,Curitiba\n"
            ."Carla Dias,carla.dias@outlook.com,Fiscalizacao,Ponta Grossa\n"
            ."Bruno Souza,bruno.souza@crcpr.org.br,Registro,Curitiba\n"
            ."Maria Santos,maria.santos@gmail.com,Fiscalizacao,Londrina\n"
            ."Elisa Rocha,elisa.rocha@gmail.com,Contabilidade,Maringa\n"
            ."Rafael Nunes,rafael.nunes@outlook.com,Registro,Cascavel\n";

        $sales = "Produto,Regiao,Quantidade,Valor,Data\n"
            ."Caneta,Sul,10,25.50,01/02/2026\n"
            ."Caderno,Sudeste,5,94.50,03/02/2026\n"
            ."Lapis,Sul,0,0.00,05/02/2026\n"
            ."Borracha,Nordeste,7,21.70,07/02/2026\n"
            ."Mochila,Sul,3,357.00,09/02/2026\n"
            ."Estojo,Sudeste,12,180.00,11/02/2026\n"
            ."Marcador,Nordeste,8,64.00,13/02/2026\n"
            ."Papel,Sul,40,220.00,15/02/2026\n"
            ."Grampeador,Centro-Oeste,4,140.00,17/02/2026\n"
            ."Fita,Nordeste,15,45.00,19/02/2026\n"
            ."Tesoura,Sul,6,72.00,21/02/2026\n"
            ."Cola,Sudeste,9,40.50,23/02/2026\n";

        $this->dump('preview-contacts', $contacts, 'contatos-crcpr.csv', $token);
        $this->dump('preview-sales', $sales, 'vendas-fevereiro.csv', $token);
    }

    private function dump(string $file, string $csv, string $name, string $token): void
    {
        $path = sys_get_temp_dir().'/'.uniqid('preview-', true).'-'.$name;
        file_put_contents($path, $csv);
        $import = app(ImportService::class)->import(
            new UploadedFile($path, $name, 'text/csv', null, true),
            hash('sha256', $token),
        );

        $response = $this->withSession([
            'datacanvas.visitor_token' => $token,
            'datacanvas.current_import' => $import->id,
        ])->get(route('dashboard', ['importId' => $import->id]));

        $response->assertOk();
        file_put_contents(public_path($file.'.html'), $response->getContent());
        @unlink($path);
    }
}
