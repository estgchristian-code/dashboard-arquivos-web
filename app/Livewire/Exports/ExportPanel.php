<?php

namespace App\Livewire\Exports;

use Livewire\Component;

class ExportPanel extends Component
{
    public string $importId = '';

    public function mount(string $importId): void
    {
        $this->importId = $importId;
    }

    public function render()
    {
        return view('livewire.exports.export-panel');
    }
}
