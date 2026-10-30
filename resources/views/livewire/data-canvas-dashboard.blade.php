<div class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
    @if ($loading)
        <div class="rounded-xl border border-line bg-surface p-8 text-center">
            <div class="mx-auto h-8 w-8 animate-spin rounded-full border-2 border-brand border-t-transparent"></div>
            <p class="mt-3 text-sm text-muted">Carregando dashboard...</p>
        </div>
    @elseif ($errorMessage !== '')
        <div class="rounded-xl border border-[#fecaca] bg-[#fef2f2] p-6 text-center">
            <p class="text-sm text-[#991b1b]">{{ $errorMessage }}</p>
            <button wire:click="goToImport" class="mt-4 inline-flex items-center justify-center rounded-md bg-brand px-4 py-2 text-sm font-medium text-white hover:bg-brand-strong">
                Voltar à página inicial
            </button>
        </div>
    @elseif ($import !== null)
        <div class="flex flex-col gap-6">
            <section class="rounded-xl border border-line bg-surface">
                <div class="flex flex-wrap items-start justify-between gap-4 border-b border-line px-4 py-4 sm:px-6">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded bg-brand-soft px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-brand">{{ $import['format_label'] }}</span>
                            <h1 class="truncate text-lg font-semibold" title="{{ $import['original_name'] }}">{{ $import['original_name'] }}</h1>
                        </div>
                        <p class="mt-1 text-xs text-muted">{{ $import['size_label'] }} · {{ $import['row_count'] }} linhas · {{ $import['column_count'] }} colunas</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button wire:click="goToImport" class="inline-flex items-center justify-center rounded-md border border-line px-3 py-1.5 text-sm font-medium text-muted hover:text-ink">
                            Ver importação
                        </button>
                        <button wire:click="importAnother" class="inline-flex items-center justify-center rounded-md bg-brand px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-strong">
                            Importar outra planilha
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-px bg-line md:grid-cols-4">
                    <div class="bg-surface px-4 py-4 sm:px-6">
                        <dt class="text-xs text-muted">Total de registros</dt>
                        <dd class="mt-1 text-2xl font-semibold">{{ number_format($import['row_count'], 0, ',', '.') }}</dd>
                    </div>
                    <div class="bg-surface px-4 py-4 sm:px-6">
                        <dt class="text-xs text-muted">Colunas</dt>
                        <dd class="mt-1 text-2xl font-semibold">{{ number_format($import['column_count'], 0, ',', '.') }}</dd>
                    </div>
                    <div class="bg-surface px-4 py-4 sm:px-6">
                        <dt class="text-xs text-muted">Células vazias</dt>
                        <dd class="mt-1 text-2xl font-semibold">{{ number_format($import['empty_cells'], 0, ',', '.') }}</dd>
                    </div>
                    <div class="bg-surface px-4 py-4 sm:px-6">
                        <dt class="text-xs text-muted">Recomendações</dt>
                        <dd class="mt-1 text-2xl font-semibold">{{ $analysis ? count($analysis['recommendations'] ?? []) : 0 }}</dd>
                    </div>
                </div>
            </section>

            @if ($analysis && ! empty($analysis['recommendations']))
                <section>
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-base font-semibold">Gráficos recomendados</h2>
                        <p class="text-xs text-muted">Gerados a partir da análise automática</p>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($analysis['recommendations'] as $recommendation)
                            <livewire:dashboard-chart :importId="$importId" :recommendation="$recommendation" :wire:key="'chart-'.$loop->index" />
                        @endforeach
                    </div>
                </section>
            @elseif ($analysis && empty($analysis['recommendations']))
                <div class="rounded-xl border border-line bg-surface p-6 text-center">
                    <p class="text-sm font-medium">Nenhum gráfico recomendado</p>
                    <p class="mt-1 text-sm text-muted">Não há combinações suficientes para gerar recomendações com os dados atuais.</p>
                    @if (! empty($analysis['issues']['insufficient_data'] ?? []))
                        <ul class="mt-3 space-y-1 text-xs text-muted">
                            @foreach ($analysis['issues']['insufficient_data'] as $issue)
                                <li>{{ $issue }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif

            <section class="rounded-xl border border-line bg-surface">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-4 py-4 sm:px-6">
                    <div>
                        <h2 class="text-base font-semibold">Dados importados</h2>
                        <p class="mt-1 text-xs text-muted">Visualização paginada dos registros reais</p>
                    </div>
                    <p class="text-xs text-muted">Total: {{ number_format($tableData['total'], 0, ',', '.') }} registros</p>
                </div>

                <div class="max-h-[28rem] overflow-auto">
                    <table class="min-w-full border-collapse text-left text-xs">
                        <thead class="sticky top-0 z-10 bg-canvas text-ink">
                            <tr>
                                <th scope="col" class="w-10 border-b border-line px-3 py-2 text-right font-medium text-muted">#</th>
                                @foreach ($import['columns'] as $column)
                                    <th scope="col" class="whitespace-nowrap border-b border-line px-3 py-2 font-semibold">
                                        {{ $column['name'] }}
                                        <span class="mt-0.5 block text-[10px] font-normal text-muted">{{ $column['type_label'] }}</span>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line bg-surface">
                            @forelse ($tableData['rows'] as $rowIndex => $row)
                                <tr class="hover:bg-canvas">
                                    <td class="px-3 py-2 text-right text-muted">{{ ($tableData['page'] - 1) * $tableData['per_page'] + $rowIndex + 1 }}</td>
                                    @foreach ($import['columns'] as $columnIndex => $column)
                                        @php($value = $row[$columnIndex] ?? '')
                                        <td class="max-w-[18rem] truncate whitespace-nowrap px-3 py-2 {{ $value === '' ? 'text-muted' : '' }}">
                                            {{ $value === '' ? '—' : $value }}
                                        </td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($import['columns']) + 1 }}" class="px-3 py-6 text-center text-sm text-muted">Sem dados para exibir.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($tableData['pages'] > 1)
                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line px-4 py-3 sm:px-6">
                        <p class="text-xs text-muted">Página {{ $tableData['page'] }} de {{ $tableData['pages'] }}</p>
                        <div class="flex items-center gap-2">
                            <button wire:click="prevPage" @if($tableData['page'] <= 1) disabled @endif class="inline-flex items-center justify-center rounded-md border border-line px-2.5 py-1 text-xs font-medium disabled:cursor-not-allowed disabled:opacity-50">Anterior</button>
                            <button wire:click="nextPage" @if($tableData['page'] >= $tableData['pages']) disabled @endif class="inline-flex items-center justify-center rounded-md border border-line px-2.5 py-1 text-xs font-medium disabled:cursor-not-allowed disabled:opacity-50">Próxima</button>
                        </div>
                    </div>
                @endif
            </section>
        </div>
    @endif
</div>
