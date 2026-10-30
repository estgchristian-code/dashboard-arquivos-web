<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class DashboardChart extends Component
{
    public string $importId = '';

    public array $recommendation = [];

    public string $chartId = '';

    public function mount(string $importId, array $recommendation): void
    {
        $this->importId = $importId;
        $this->recommendation = $recommendation;
        $this->chartId = 'chart-'.uniqid();
    }

    public function render(): View
    {
        return view('livewire.dashboard-chart');
    }
}
