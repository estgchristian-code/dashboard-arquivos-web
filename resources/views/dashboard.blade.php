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
            loadImage(source) {
                return new Promise((resolve, reject) => {
                    const image = new Image();
                    image.onload = () => resolve(image);
                    image.onerror = () => reject(new Error('Não foi possível carregar um dos gráficos.'));
                    image.src = source;
                });
            },

            collectChartImages() {
                const echarts = window.DataCanvas?.echarts;
                const images = [];

                if (echarts) {
                    document.querySelectorAll('[_echarts_instance_]').forEach((container) => {
                        const chart = echarts.getInstanceByDom(container);

                        if (! chart) {
                            return;
                        }

                        try {
                            images.push(chart.getDataURL({ type: 'png', pixelRatio: 2, backgroundColor: '#ffffff' }));
                        } catch (error) {
                            // Um gráfico inválido não deve impedir a exportação dos demais.
                        }
                    });
                }

                if (images.length === 0) {
                    document.querySelectorAll('canvas').forEach((canvas) => {
                        try {
                            if (canvas.width > 0 && canvas.height > 0) {
                                images.push(canvas.toDataURL('image/png'));
                            }
                        } catch (error) {
                            // Canvas ainda não renderizado ou protegido pelo navegador.
                        }
                    });
                }

                return images;
            },

            async combineChartImages(images) {
                const loaded = await Promise.all(images.map((source) => this.loadImage(source)));
                const gap = 16;
                const width = Math.max(...loaded.map((image) => image.naturalWidth || image.width));
                const height = loaded.reduce((total, image) => total + (image.naturalHeight || image.height), 0) + gap * (loaded.length - 1);

                const canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;

                const context = canvas.getContext('2d');
                context.fillStyle = '#ffffff';
                context.fillRect(0, 0, width, height);

                let offset = 0;
                loaded.forEach((image) => {
                    const imageWidth = image.naturalWidth || image.width;
                    const imageHeight = image.naturalHeight || image.height;
                    context.drawImage(image, Math.round((width - imageWidth) / 2), offset, imageWidth, imageHeight);
                    offset += imageHeight + gap;
                });

                return canvas.toDataURL('image/png');
            },

            async exportAll() {
                const importId = document.querySelector('[data-import-id]')?.getAttribute('data-import-id') || @json($importId ?? '');
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

                if (! importId) {
                    window.alert('Importação não identificada. Recarregue o dashboard e tente novamente.');
                    return;
                }

                const images = this.collectChartImages().filter(Boolean);

                if (images.length === 0) {
                    window.alert('Nenhum gráfico disponível para exportar.');
                    return;
                }

                try {
                    const image = images.length === 1 ? images[0] : await this.combineChartImages(images);
                    const formData = new FormData();
                    formData.append('_token', token);
                    formData.append('image', image);

                    const response = await fetch('/exports/' + encodeURIComponent(importId) + '/png', {
                        method: 'POST',
                        headers: {
                            'Accept': 'image/png',
                            'X-CSRF-TOKEN': token,
                        },
                        body: formData,
                        credentials: 'same-origin',
                    });

                    if (! response.ok) {
                        const message = await response.text();
                        throw new Error(message || 'Falha ao gerar a imagem.');
                    }

                    const blob = await response.blob();
                    const url = window.URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    link.href = url;
                    link.download = 'graficos-' + new Date().toISOString().slice(0, 10) + '.png';
                    document.body.appendChild(link);
                    link.click();
                    link.remove();
                    window.URL.revokeObjectURL(url);
                } catch (error) {
                    window.alert(error.message || 'Não foi possível exportar os gráficos.');
                }
            },
        };
    </script>
@endsection
