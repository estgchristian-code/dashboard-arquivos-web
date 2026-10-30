<?php

namespace Tests\Feature\Imports;

use App\Livewire\ImportUploader;
use App\Services\Imports\VisitorIdentity;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

class ImportUploaderTest extends ImportTestCase
{
    public function test_visitor_can_upload_a_real_csv_and_see_the_preview(): void
    {
        $component = Livewire::test(ImportUploader::class)
            ->set('file', $this->livewireUpload('valid.csv'));

        $component->assertSet('errorMessage', '')
            ->assertSee('valid.csv')
            ->assertSee('Linhas')
            ->assertSee('Caneta')
            ->assertSee('Remover e enviar outro');

        $id = session('datacanvas.current_import');

        $this->assertIsString($id);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $id);
        $this->assertTrue(Storage::disk('imports')->exists($id.'/metadata.json'));
    }

    public function test_visitor_sees_a_clear_error_for_an_invalid_file(): void
    {
        Livewire::test(ImportUploader::class)
            ->set('file', $this->livewireUpload('fake-image.csv'))
            ->assertSet('errorMessage', 'O conteúdo do arquivo não parece ser textual.')
            ->assertSee('O conteúdo do arquivo não parece ser textual.')
            ->assertSet('import', null)
            ->assertSee('Arraste e solte sua planilha aqui');
    }

    public function test_visitor_can_remove_the_import_and_start_again(): void
    {
        $component = Livewire::test(ImportUploader::class)
            ->set('file', $this->livewireUpload('valid.csv'));

        $id = session('datacanvas.current_import');
        $this->assertTrue(Storage::disk('imports')->exists($id.'/metadata.json'));

        $component->call('remove')
            ->assertSet('import', null)
            ->assertSee('Arraste e solte sua planilha aqui');

        $this->assertNull(session('datacanvas.current_import'));
        $this->assertFalse(Storage::disk('imports')->exists($id.'/metadata.json'));

        $component->set('file', $this->livewireUpload('valid.tsv'))
            ->assertSee('valid.tsv')
            ->assertSee('Curitiba');
    }

    public function test_previous_import_is_restored_from_the_visitor_session(): void
    {
        $token = app(VisitorIdentity::class)->token();
        $import = $this->importFixture('valid.xlsx', token: $token);

        session(['datacanvas.current_import' => $import->id]);

        Livewire::test(ImportUploader::class)
            ->assertSet('import.id', $import->id)
            ->assertSee('valid.xlsx')
            ->assertSee('Vendas');
    }

    public function test_session_pointing_to_unknown_import_is_discarded(): void
    {
        session(['datacanvas.current_import' => str_repeat('b', 32)]);

        Livewire::test(ImportUploader::class)
            ->assertSet('import', null);

        $this->assertNull(session('datacanvas.current_import'));
    }

    public function test_session_pointing_to_another_visitors_import_is_discarded(): void
    {
        $import = $this->importFixture('valid.csv', token: 'outro-visitante');

        session(['datacanvas.current_import' => $import->id]);

        Livewire::test(ImportUploader::class)
            ->assertSet('import', null)
            ->assertDontSee('Caneta');

        $this->assertNull(session('datacanvas.current_import'));
        $this->assertTrue(Storage::disk('imports')->exists($import->id.'/metadata.json'));
    }

    public function test_script_payloads_are_escaped_in_the_preview(): void
    {
        $html = Livewire::test(ImportUploader::class)
            ->set('file', $this->livewireUpload('xss.csv'))
            ->html();

        $this->assertStringNotContainsString("<script>alert('x')</script>", $html);
        $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('&lt;img', $html);
    }

    public function test_component_rate_limit_blocks_repeated_uploads(): void
    {
        config()->set('datacanvas.rate_limit.enabled', true);
        config()->set('datacanvas.rate_limit.per_minute', 1);
        config()->set('datacanvas.rate_limit.per_hour', 100);

        $component = Livewire::test(ImportUploader::class)
            ->set('file', $this->livewireUpload('valid.csv'));

        $this->assertSame('', $component->get('errorMessage'));

        $component->set('file', $this->livewireUpload('valid.tsv'))
            ->assertSee('Muitas tentativas de importação');

        $this->assertStringContainsString('Muitas tentativas', $component->get('errorMessage'));
    }

    public function test_unsupported_extension_shows_a_clear_error(): void
    {
        Livewire::test(ImportUploader::class)
            ->set('file', $this->livewireUpload('valid.csv', 'planilha.txt'))
            ->assertSee('A extensão do arquivo não é suportada');
    }

    public function test_temporary_upload_rejection_shows_a_clear_limit_message(): void
    {
        config()->set('livewire.temporary_file_upload.rules', ['required', 'file', 'mimes:png']);

        Livewire::test(ImportUploader::class)
            ->set('file', $this->livewireUpload('valid.csv'))
            ->assertSee('Não foi possível enviar o arquivo.')
            ->assertSee('no máximo')
            ->assertSet('import', null);
    }
}
