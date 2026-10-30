<div class="rounded-xl border border-line bg-surface">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-4 py-4 sm:px-6">
        <div>
            <h2 class="text-base font-semibold">Exportar</h2>
            <p class="mt-1 text-xs text-muted">Baixe dados e relatórios em diferentes formatos</p>
        </div>
    </div>
    <div class="flex flex-wrap items-center gap-2 px-4 py-4 sm:px-6">
        <a href="{{ route('exports.csv', ['importId' => $importId]) }}" class="inline-flex items-center justify-center rounded-md border border-line px-3 py-1.5 text-sm font-medium hover:text-ink">CSV</a>
        <a href="{{ route('exports.json', ['importId' => $importId]) }}" class="inline-flex items-center justify-center rounded-md border border-line px-3 py-1.5 text-sm font-medium hover:text-ink">JSON</a>
        <a href="{{ route('exports.pdf', ['importId' => $importId]) }}" class="inline-flex items-center justify-center rounded-md border border-line px-3 py-1.5 text-sm font-medium hover:text-ink">PDF</a>
        <button type="button" x-data x-on:click="window.DataCanvasExport?.exportAll?.()" class="inline-flex items-center justify-center rounded-md bg-brand px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-strong">Exportar gráficos (PNG)</button>
    </div>
</div>
