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
        // Alinha o limite de transporte do Livewire ao limite real de importação.
        $maxKilobytes = max(1, intdiv((int) config('datacanvas.limits.max_file_bytes', 10 * 1024 * 1024), 1024));

        config([
            'livewire.temporary_file_upload.rules' => ['required', 'file', 'max:'.$maxKilobytes],
        ]);
    }
}
