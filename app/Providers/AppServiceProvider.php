<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // O TCPDF 7 usa os arquivos de fonte do tc-lib-pdf-font. Sem apontar
        // K_PATH_FONTS para as fontes empacotadas na aplicação, a geração de
        // PDF falha com "unable to read file: helvetica.json".
        if (! defined('K_PATH_FONTS')) {
            define('K_PATH_FONTS', resource_path('fonts').DIRECTORY_SEPARATOR);
        }

        // Alinha o limite de transporte do Livewire ao limite real de importação.
        $maxKilobytes = max(1, intdiv((int) config('datacanvas.limits.max_file_bytes', 10 * 1024 * 1024), 1024));

        config([
            'livewire.temporary_file_upload.rules' => ['required', 'file', 'max:'.$maxKilobytes],
        ]);
    }
}
