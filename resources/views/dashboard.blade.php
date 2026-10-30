@extends('layouts.app')

@section('title', 'Dashboard — DataCanvas')

@section('content')
    <header class="border-b border-line bg-surface">
        <div class="mx-auto flex h-16 w-full max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
            <a href="{{ route('home') }}" class="flex items-center gap-2.5" aria-label="DataCanvas — página inicial">
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand text-white" aria-hidden="true">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 19V10" />
                        <path d="M10 19V5" />
                        <path d="M16 19v-6" />
                        <path d="M22 19H2" />
                    </svg>
                </span>
                <span class="text-lg font-semibold tracking-tight">DataCanvas</span>
            </a>

            <div class="flex items-center gap-3">
                <a href="{{ route('home') }}" class="inline-flex items-center justify-center rounded-md border border-line bg-surface px-3 py-1.5 text-sm font-medium text-muted transition-colors hover:text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">
                    Voltar à página inicial
                </a>
            </div>
        </div>
    </header>

    <main id="conteudo" class="flex-1 bg-canvas">
        <livewire:data-canvas-dashboard :importId="$importId" />
    </main>

    <footer class="border-t border-line bg-surface">
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-2 px-4 py-6 text-sm text-muted sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
            <p>DataCanvas — análise de planilhas sem cadastro.</p>
            <p>Projeto em desenvolvimento · {{ date('Y') }}</p>
        </div>
    </footer>

    <script>
        window.DataCanvasExport = {
            async exportAll() {
                const charts = document.querySelectorAll('[wire\\:ignore] canvas, [wire\\:ignore] > div');
                // Try to find echarts instances
                if (window.echarts) {
                    // Fallback
                }
                const instances = [];
                // ECharts stores instances; also try DataCanvas
                if (window.DataCanvas && window.DataCanvas.echarts) {
                    // not directly accessible
                }
                // Simple approach: get all canvases and convert
                const canvases = document.querySelectorAll('canvas');
                for (let i = 0; i < canvases.length; i++) {
                    try {
                        const dataUrl = canvases[i].toDataURL('image/png');
                        const formData = new FormData();
                        formData.append('image', dataUrl);
                        formData.append('_token', document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '');
                        const importId = document.querySelector('[data-import-id]')?.getAttribute('data-import-id') || '{{ $importId ?? "" }}';
                        if (!importId) continue;
                        fetch('/exports/' + importId + '/png', {
                            method: 'POST',
                            body: formData,
                            credentials: 'same-origin'
                        }).then(r => r.blob()).then(blob => {
                            const url = window.URL.createObjectURL(blob);
                            const a = document.createElement('a');
                            a.href = url;
                            a.download = 'grafico-' + (i+1) + '.png';
                            document.body.appendChild(a);
                            a.click();
                            a.remove();
                        });
                    } catch(e) {}
                }
                alert('Exportação de gráficos iniciada.');
            }
        };
    </script>
@endsection
