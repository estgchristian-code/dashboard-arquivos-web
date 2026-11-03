<div class="mx-auto w-full max-w-[92rem] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
    @if ($loading)
        <div class="rounded-2xl border border-line bg-surface p-10 text-center shadow-sm">
            <div class="mx-auto h-9 w-9 animate-spin rounded-full border-2 border-brand border-t-transparent"></div>
            <p class="mt-4 text-sm text-muted">Preparando seu dashboard...</p>
        </div>
    @elseif ($errorMessage !== '')
        <div class="rounded-2xl border border-[#fecaca] bg-[#fef2f2] p-8 text-center shadow-sm">
            <p class="text-sm font-medium text-[#991b1b]">{{ $errorMessage }}</p>
            <button wire:click="goToImport" class="mt-5 inline-flex items-center justify-center rounded-lg bg-brand px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-brand-strong">
                Voltar à página inicial
            </button>
        </div>
    @elseif ($import !== null)
        <div class="flex flex-col gap-6" data-import-id="{{ $importId }}">
            {{-- Hero --}}
            <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-indigo-950 via-indigo-900 to-teal-900 text-white shadow-2xl shadow-indigo-950/20">
                <div class="pointer-events-none absolute -right-24 -top-32 h-80 w-80 rounded-full bg-fuchsia-500/25 blur-3xl" aria-hidden="true"></div>
                <div class="pointer-events-none absolute -bottom-40 left-1/3 h-96 w-96 rounded-full bg-teal-400/20 blur-3xl" aria-hidden="true"></div>

                <div class="relative flex flex-col gap-8 p-6 sm:p-8 lg:flex-row lg:items-end lg:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.14em] text-indigo-100 backdrop-blur">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-300" aria-hidden="true"></span>
                                Dashboard automático
                            </span>
                            <span class="rounded-full bg-white/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.12em] text-indigo-100">{{ $import['format_label'] }}</span>
                        </div>

                        <h1 class="mt-4 max-w-3xl break-words text-2xl font-semibold tracking-tight text-white sm:text-3xl lg:text-4xl" title="{{ $import['original_name'] }}">
                            {{ $import['original_name'] }}
                        </h1>

                        <div class="mt-5 flex flex-wrap items-center gap-x-5 gap-y-2 text-xs text-indigo-100/80">
                            <span class="inline-flex items-center gap-1.5">
                                <svg class="h-4 w-4 text-indigo-200" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M3 3.5A1.5 1.5 0 0 1 4.5 2h11A1.5 1.5 0 0 1 17 3.5v13A1.5 1.5 0 0 1 15.5 18h-11A1.5 1.5 0 0 1 3 16.5v-13Z" opacity=".3"/><path d="M6 6h8v1.5H6V6Zm0 3h8v1.5H6V9Zm0 3h5v1.5H6V12Z"/></svg>
                                {{ number_format($import['row_count'], 0, ',', '.') }} registros
                            </span>
                            <span class="inline-flex items-center gap-1.5">
                                <svg class="h-4 w-4 text-indigo-200" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M4 3.5A1.5 1.5 0 0 1 5.5 2h9A1.5 1.5 0 0 1 16 3.5v13a1.5 1.5 0 0 1-1.5 1.5h-9A1.5 1.5 0 0 1 4 16.5v-13ZM7 6h6v1.5H7V6Zm0 3h6v1.5H7V9Zm0 3h4v1.5H7V12Z"/></svg>
                                {{ number_format($import['column_count'], 0, ',', '.') }} colunas
                            </span>
                            <span class="inline-flex items-center gap-1.5">
                                <svg class="h-4 w-4 text-indigo-200" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 2a8 8 0 1 0 0 16 8 8 0 0 0 0-16Zm.75 4.25a.75.75 0 0 0-1.5 0V10c0 .2.08.39.22.53l2.5 2.5a.75.75 0 1 0 1.06-1.06l-2.28-2.28V6.25Z"/></svg>
                                {{ $import['size_label'] }} · expira em {{ $import['expires_at'] }}
                            </span>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 lg:justify-end">
                        <button wire:click="goToImport" class="inline-flex items-center justify-center rounded-xl border border-white/20 bg-white/10 px-4 py-2.5 text-sm font-medium text-white backdrop-blur transition-colors hover:bg-white/15">
                            Ver importação
                        </button>
                        <button wire:click="importAnother" class="inline-flex items-center justify-center rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-indigo-950 shadow-lg shadow-black/10 transition-colors hover:bg-indigo-50">
                            Importar outra planilha
                        </button>
                    </div>
                </div>
            </section>

            {{-- KPIs --}}
            <section aria-label="Indicadores principais" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
                @foreach ($kpis as $kpi)
                    @php
                        $tone = match ($kpi['tone'] ?? 'indigo') {
                            'violet' => ['bar' => 'from-violet-500 to-fuchsia-500', 'icon' => 'bg-violet-50 text-violet-600'],
                            'emerald' => ['bar' => 'from-emerald-500 to-teal-500', 'icon' => 'bg-emerald-50 text-emerald-600'],
                            'sky' => ['bar' => 'from-sky-500 to-blue-500', 'icon' => 'bg-sky-50 text-sky-600'],
                            'cyan' => ['bar' => 'from-cyan-500 to-teal-500', 'icon' => 'bg-cyan-50 text-cyan-600'],
                            'amber' => ['bar' => 'from-amber-500 to-orange-500', 'icon' => 'bg-amber-50 text-amber-600'],
                            'rose' => ['bar' => 'from-rose-500 to-pink-500', 'icon' => 'bg-rose-50 text-rose-600'],
                            default => ['bar' => 'from-indigo-500 to-blue-500', 'icon' => 'bg-indigo-50 text-indigo-600'],
                        };
                    @endphp
                    <article class="group relative overflow-hidden rounded-2xl border border-line bg-surface p-5 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-lg hover:shadow-slate-200/70">
                        <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r {{ $tone['bar'] }}" aria-hidden="true"></div>
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-muted">{{ $kpi['label'] }}</p>
                                <p class="mt-2 break-words text-2xl font-bold tracking-tight text-ink">{{ $kpi['value'] }}</p>
                                <p class="mt-1 line-clamp-2 text-xs text-muted">{{ $kpi['hint'] }}</p>
                            </div>
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $tone['icon'] }}">
                                @switch($kpi['icon'])
                                    @case('rows')
                                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M3 4.5A1.5 1.5 0 0 1 4.5 3h11A1.5 1.5 0 0 1 17 4.5v2A1.5 1.5 0 0 1 15.5 8h-11A1.5 1.5 0 0 1 3 6.5v-2Zm0 6A1.5 1.5 0 0 1 4.5 9h11a1.5 1.5 0 0 1 1.5 1.5v2a1.5 1.5 0 0 1-1.5 1.5h-11A1.5 1.5 0 0 1 3 12.5v-2Zm0 6A1.5 1.5 0 0 1 4.5 15h11a1.5 1.5 0 0 1 1.5 1.5v.5H3v-.5Z"/></svg>
                                        @break
                                    @case('columns')
                                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M3 4.5A1.5 1.5 0 0 1 4.5 3h3A1.5 1.5 0 0 1 9 4.5v11A1.5 1.5 0 0 1 7.5 17h-3A1.5 1.5 0 0 1 3 15.5v-11Zm8 0A1.5 1.5 0 0 1 12.5 3h3A1.5 1.5 0 0 1 17 4.5v6A1.5 1.5 0 0 1 15.5 12h-3A1.5 1.5 0 0 1 11 10.5v-6Zm0 9.5A1.5 1.5 0 0 1 12.5 13h3a1.5 1.5 0 0 1 1.5 1.5v1a1.5 1.5 0 0 1-1.5 1.5h-3a1.5 1.5 0 0 1-1.5-1.5v-1Z"/></svg>
                                        @break
                                    @case('gauge')
                                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 3a7 7 0 0 0-6.99 7.37.75.75 0 0 0 .75.63h1.5a.75.75 0 0 0 .74-.66A4.5 4.5 0 1 1 14.5 10a.75.75 0 0 0 .75.75h1.49a.75.75 0 0 0 .75-.79A7 7 0 0 0 10 3Zm3.28 4.22a.75.75 0 0 0-1.06 0l-2.6 2.6a1.5 1.5 0 1 0 1.06 1.06l2.6-2.6a.75.75 0 0 0 0-1.06Z"/></svg>
                                        @break
                                    @case('mail')
                                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M2 5.5A1.5 1.5 0 0 1 3.5 4h13A1.5 1.5 0 0 1 18 5.5v.28l-8 4.8-8-4.8V5.5Zm0 2.74V14.5A1.5 1.5 0 0 0 3.5 16h13a1.5 1.5 0 0 0 1.5-1.5V8.24l-7.53 4.52a1 1 0 0 1-1.02 0L2 8.24Z"/></svg>
                                        @break
                                    @case('tag')
                                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M2.5 3.5A1 1 0 0 1 3.5 2.5h5.17a2 2 0 0 1 1.42.59l7.16 7.16a2 2 0 0 1 0 2.82l-4.18 4.18a2 2 0 0 1-2.82 0L3.09 10.09A2 2 0 0 1 2.5 8.67V3.5Zm4.25 3a1.25 1.25 0 1 0 0-2.5 1.25 1.25 0 0 0 0 2.5Z"/></svg>
                                        @break
                                    @case('users')
                                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M7 9a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm7.5 1a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5ZM1.5 15.5A4.5 4.5 0 0 1 6 11h2a4.5 4.5 0 0 1 4.5 4.5.5.5 0 0 1-.5.5H2a.5.5 0 0 1-.5-.5Zm13.02-3.5A3.5 3.5 0 0 1 18 15.5a.5.5 0 0 1-.5.5h-3.65a5.48 5.48 0 0 0-1.1-3.28c.53-.14 1.08-.22 1.65-.22h.12Z"/></svg>
                                        @break
                                    @case('sum')
                                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M4 3.5A.5.5 0 0 1 4.5 3h11a.5.5 0 0 1 0 1H6.62l4.3 6-4.3 6H15.5a.5.5 0 0 1 0 1h-11a.5.5 0 0 1-.4-.8l4.77-6.2L4.1 3.8A.5.5 0 0 1 4 3.5Z"/></svg>
                                        @break
                                    @case('calendar')
                                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M5.5 2a.75.75 0 0 1 .75.75V4h7.5V2.75a.75.75 0 0 1 1.5 0V4h.25A1.5 1.5 0 0 1 17 5.5v10a1.5 1.5 0 0 1-1.5 1.5h-11A1.5 1.5 0 0 1 3 15.5v-10A1.5 1.5 0 0 1 4.5 4h.25V2.75A.75.75 0 0 1 5.5 2Zm-1 6v7.5h11V8h-11Z"/></svg>
                                        @break
                                    @default
                                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 2a8 8 0 1 0 0 16 8 8 0 0 0 0-16Zm1 4v4.59l2.7 2.7-1.4 1.42L9 11.4V6h2Z"/></svg>
                                @endswitch
                            </span>
                        </div>
                    </article>
                @endforeach
            </section>

            {{-- Filters --}}
            <section class="rounded-2xl border border-line bg-surface p-5 shadow-sm sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-semibold text-ink">Explorar dados</h2>
                        <p class="mt-1 text-xs text-muted">Busque e combine filtros para recalcular indicadores e gráficos em tempo real.</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center gap-2 rounded-full border border-line bg-canvas px-3 py-1 text-xs font-medium text-muted">
                            <span class="h-1.5 w-1.5 rounded-full bg-indigo-500" aria-hidden="true"></span>
                            {{ number_format($visibleRecords, 0, ',', '.') }} de {{ number_format($totalRecords, 0, ',', '.') }} registros
                        </span>
                        @if ($isFiltered)
                            <button wire:click="clearFilters" class="inline-flex items-center gap-1.5 rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700 transition-colors hover:bg-indigo-100">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg>
                                Limpar filtros
                            </button>
                        @endif
                    </div>
                </div>

                <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <label class="block md:col-span-2 xl:col-span-1">
                        <span class="mb-1.5 block text-xs font-medium text-muted">Buscar em todos os campos</span>
                        <span class="relative block">
                            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 3.47 9.77l3.13 3.13a.75.75 0 1 0 1.06-1.06l-3.13-3.13A5.5 5.5 0 0 0 9 3.5ZM5 9a4 4 0 1 1 8 0 4 4 0 0 1-8 0Z" clip-rule="evenodd"/></svg>
                            <input
                                type="search"
                                wire:model.live.debounce.400ms="search"
                                placeholder="Nome, e-mail, categoria..."
                                class="w-full rounded-xl border border-line bg-canvas py-2 pl-9 pr-3 text-sm text-ink placeholder:text-muted/70 focus:border-indigo-400 focus:bg-surface focus:outline-none focus:ring-2 focus:ring-indigo-100"
                            >
                        </span>
                    </label>

                    @foreach ($filterOptions as $option)
                        <label class="block">
                            <span class="mb-1.5 block truncate text-xs font-medium text-muted" title="{{ $option['label'] }}">{{ $option['label'] }}</span>
                            <select
                                wire:model.live="filters.{{ $option['key'] }}"
                                class="w-full rounded-xl border border-line bg-canvas px-3 py-2 text-sm text-ink focus:border-indigo-400 focus:bg-surface focus:outline-none focus:ring-2 focus:ring-indigo-100"
                            >
                                <option value="">Todos</option>
                                @foreach ($option['values'] as $value)
                                    <option value="{{ $value }}">{{ $value }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endforeach
                </div>
            </section>

            <div class="grid min-w-0 gap-6 xl:grid-cols-[minmax(0,1fr)_21rem]">
                <div class="flex min-w-0 flex-col gap-6">
                    {{-- Charts --}}
                    <section>
                        <div class="mb-4 flex flex-wrap items-end justify-between gap-2">
                            <div>
                                <h2 class="text-lg font-semibold tracking-tight text-ink">Painel visual</h2>
                                <p class="mt-1 text-xs text-muted">Visualizações escolhidas a partir dos tipos e agrupamentos reais da planilha.</p>
                            </div>
                            <span class="rounded-full border border-line bg-surface px-3 py-1 text-xs font-medium text-muted">
                                {{ number_format(count($dashboardAnalysis['recommendations'] ?? []), 0, ',', '.') }} visualizações
                            </span>
                        </div>

                        @if (! empty($dashboardAnalysis['recommendations']))
                            <div class="grid gap-5 md:grid-cols-2">
                                @foreach ($dashboardAnalysis['recommendations'] as $recommendation)
                                    <div class="{{ $loop->first && count($dashboardAnalysis['recommendations']) > 1 ? 'md:col-span-2' : '' }}">
                                        <livewire:dashboard-chart
                                            :importId="$importId"
                                            :recommendation="$recommendation"
                                            :wire:key="'chart-'.$viewKey.'-'.$loop->index"
                                        />
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="rounded-2xl border border-dashed border-line bg-surface p-8 text-center">
                                <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-500">
                                    <svg class="h-6 w-6" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M3 3.5A1.5 1.5 0 0 1 4.5 2h11A1.5 1.5 0 0 1 17 3.5v13a1.5 1.5 0 0 1-1.5 1.5h-11A1.5 1.5 0 0 1 3 16.5v-13ZM6 13.5V9h2v4.5H6Zm3.5 0V6h2v7.5h-2Zm3.5 0V8h2v5.5h-2Z"/></svg>
                                </span>
                                <p class="mt-3 text-sm font-semibold text-ink">Nenhum gráfico para este recorte</p>
                                <p class="mx-auto mt-1 max-w-md text-sm text-muted">
                                    @if ($isFiltered)
                                        Os filtros atuais deixaram poucos registros ou combinações. Ajuste ou limpe os filtros para ver mais visualizações.
                                    @else
                                        Não há combinações suficientes para gerar gráficos com segurança. Os registros continuam disponíveis na tabela abaixo.
                                    @endif
                                </p>
                                @if (! empty($dashboardAnalysis['issues']['insufficient_data'] ?? []))
                                    <ul class="mx-auto mt-4 max-w-md space-y-1 text-left text-xs text-muted">
                                        @foreach ($dashboardAnalysis['issues']['insufficient_data'] as $issue)
                                            <li class="flex gap-2"><span class="text-amber-500">•</span><span>{{ $issue }}</span></li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        @endif
                    </section>

                    {{-- Secondary table --}}
                    <section class="overflow-hidden rounded-2xl border border-line bg-surface shadow-sm">
                        <button
                            type="button"
                            wire:click="toggleTable"
                            class="flex w-full flex-wrap items-center justify-between gap-3 px-5 py-4 text-left transition-colors hover:bg-canvas sm:px-6"
                            aria-expanded="{{ $showTable ? 'true' : 'false' }}"
                        >
                            <span class="flex min-w-0 items-center gap-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600">
                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M3 4.5A1.5 1.5 0 0 1 4.5 3h11A1.5 1.5 0 0 1 17 4.5v11a1.5 1.5 0 0 1-1.5 1.5h-11A1.5 1.5 0 0 1 3 15.5v-11ZM5 6v2h4V6H5Zm6 0v2h4V6h-4ZM5 10v4h4v-4H5Zm6 0v4h4v-4h-4Z"/></svg>
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold text-ink">Registros completos</span>
                                    <span class="mt-0.5 block text-xs text-muted">Tabela secundária para conferir e ordenar os dados originais.</span>
                                </span>
                            </span>
                            <span class="flex items-center gap-3">
                                <span class="rounded-full border border-line bg-canvas px-3 py-1 text-xs font-medium text-muted">{{ number_format($tableData['total'], 0, ',', '.') }} registros</span>
                                <svg class="h-4 w-4 text-muted transition-transform {{ $showTable ? 'rotate-180' : '' }}" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd"/></svg>
                            </span>
                        </button>

                        @if ($showTable)
                            <div class="border-t border-line">
                                <div class="max-h-[34rem] overflow-auto">
                                    <table class="min-w-full border-collapse text-left text-xs">
                                        <thead class="sticky top-0 z-10 bg-canvas text-ink shadow-sm">
                                            <tr>
                                                <th scope="col" class="w-12 border-b border-line px-3 py-3 text-right font-medium text-muted">#</th>
                                                @foreach ($import['columns'] as $columnIndex => $column)
                                                    <th scope="col" class="whitespace-nowrap border-b border-line px-3 py-3">
                                                        <button type="button" wire:click="sortByColumn({{ $columnIndex }})" class="flex items-center gap-1.5 font-semibold text-ink transition-colors hover:text-indigo-600">
                                                            {{ $column['name'] }}
                                                            @if ($sortBy === 'col_'.$columnIndex)
                                                                <span class="text-indigo-600">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                                            @else
                                                                <span class="text-muted/40">↕</span>
                                                            @endif
                                                        </button>
                                                        <span class="mt-1 block text-[10px] font-normal uppercase tracking-wide text-muted">{{ $column['type_label'] }}</span>
                                                    </th>
                                                @endforeach
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-line bg-surface">
                                            @forelse ($tableData['rows'] as $rowIndex => $row)
                                                <tr class="transition-colors hover:bg-canvas/80">
                                                    <td class="px-3 py-2.5 text-right font-mono text-[11px] text-muted">{{ ($tableData['page'] - 1) * $tableData['per_page'] + $rowIndex + 1 }}</td>
                                                    @foreach ($import['columns'] as $columnIndex => $column)
                                                        @php($value = $row[$columnIndex] ?? '')
                                                        <td class="max-w-[20rem] truncate whitespace-nowrap px-3 py-2.5 {{ $value === '' ? 'text-muted/60' : 'text-ink' }}" title="{{ $value }}">
                                                            {{ $value === '' ? '—' : $value }}
                                                        </td>
                                                    @endforeach
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="{{ count($import['columns']) + 1 }}" class="px-3 py-10 text-center text-sm text-muted">
                                                        Nenhum registro corresponde aos filtros atuais.
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>

                                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line px-5 py-3 sm:px-6">
                                    <p class="text-xs text-muted">
                                        Exibindo
                                        <span class="font-medium text-ink">{{ $tableData['total'] > 0 ? number_format(($tableData['page'] - 1) * $tableData['per_page'] + 1, 0, ',', '.') : 0 }}</span>
                                        a
                                        <span class="font-medium text-ink">{{ number_format(min($tableData['page'] * $tableData['per_page'], $tableData['total']), 0, ',', '.') }}</span>
                                        de
                                        <span class="font-medium text-ink">{{ number_format($tableData['total'], 0, ',', '.') }}</span>
                                    </p>
                                    <div class="flex items-center gap-2">
                                        <button wire:click="prevPage" @disabled($tableData['page'] <= 1) class="inline-flex items-center justify-center rounded-lg border border-line px-3 py-1.5 text-xs font-medium text-muted transition-colors hover:bg-canvas hover:text-ink disabled:cursor-not-allowed disabled:opacity-40">Anterior</button>
                                        <span class="rounded-lg bg-canvas px-3 py-1.5 text-xs font-medium text-muted">{{ $tableData['page'] }} / {{ $tableData['pages'] }}</span>
                                        <button wire:click="nextPage" @disabled($tableData['page'] >= $tableData['pages']) class="inline-flex items-center justify-center rounded-lg border border-line px-3 py-1.5 text-xs font-medium text-muted transition-colors hover:bg-canvas hover:text-ink disabled:cursor-not-allowed disabled:opacity-40">Próxima</button>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </section>
                </div>

                <aside class="flex min-w-0 flex-col gap-6">
                    {{-- Insights --}}
                    <section class="overflow-hidden rounded-2xl border border-indigo-100 bg-gradient-to-br from-indigo-50 via-white to-teal-50 p-5 shadow-sm">
                        <div class="flex items-center gap-2">
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-600 text-white">
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 2a6 6 0 0 0-3.5 10.87c.31.23.5.6.5.99v.64c0 .55.45 1 1 1h4c.55 0 1-.45 1-1v-.64c0-.39.19-.76.5-.99A6 6 0 0 0 10 2ZM8 17a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 0-1h-3A.5.5 0 0 0 8 17Z"/></svg>
                            </span>
                            <h2 class="text-sm font-semibold text-ink">Leitura rápida</h2>
                        </div>
                        @if (! empty($insights))
                            <ol class="mt-4 space-y-3">
                                @foreach ($insights as $insight)
                                    <li class="flex gap-3 text-xs leading-5 text-slate-600">
                                        <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-white text-[10px] font-bold text-indigo-600 shadow-sm">{{ $loop->iteration }}</span>
                                        <span>{{ $insight }}</span>
                                    </li>
                                @endforeach
                            </ol>
                        @else
                            <p class="mt-4 text-xs text-muted">Ainda não há informações suficientes para gerar uma leitura automática deste recorte.</p>
                        @endif
                    </section>

                    {{-- Export --}}
                    <livewire:exports.export-panel :importId="$importId" :wire:key="'export-panel-'.$importId" />

                    {{-- Columns --}}
                    <section class="rounded-2xl border border-line bg-surface p-5 shadow-sm">
                        <div class="flex items-center justify-between gap-2">
                            <h2 class="text-sm font-semibold text-ink">Colunas identificadas</h2>
                            <span class="rounded-full bg-canvas px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-muted">{{ count($columnInsights) }}</span>
                        </div>
                        <ul class="mt-4 divide-y divide-line">
                            @foreach ($columnInsights as $column)
                                <li class="py-2.5 first:pt-0 last:pb-0">
                                    <div class="flex items-start justify-between gap-2">
                                        <p class="min-w-0 truncate text-xs font-medium text-ink" title="{{ $column['name'] }}">{{ $column['name'] }}</p>
                                        <span class="shrink-0 rounded bg-canvas px-1.5 py-0.5 text-[10px] font-medium text-muted">{{ $column['type_label'] }}</span>
                                    </div>
                                    <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                        @foreach ($column['flags'] as $flag)
                                            <span class="rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-semibold text-indigo-600">{{ $flag }}</span>
                                        @endforeach
                                        <span class="text-[10px] text-muted">{{ number_format($column['distinct_count'], 0, ',', '.') }} únicos</span>
                                        @if ($column['empty_count'] > 0)
                                            <span class="text-[10px] text-amber-600">{{ number_format($column['empty_count'], 0, ',', '.') }} vazios</span>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </section>

                    {{-- Data quality --}}
                    @if (! empty($dashboardAnalysis['issues']['inconsistent'] ?? []))
                        <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm">
                            <div class="flex items-center gap-2">
                                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-500 text-white">
                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.49 2.94a1.75 1.75 0 0 1 3.02 0l6.02 10.42A1.75 1.75 0 0 1 16.02 16H3.98a1.75 1.75 0 0 1-1.51-2.64L8.49 2.94ZM10 6a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 6Zm0 7.5a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/></svg>
                                </span>
                                <h2 class="text-sm font-semibold text-amber-900">Pontos de atenção</h2>
                            </div>
                            <ul class="mt-3 space-y-2 text-xs leading-5 text-amber-800">
                                @foreach ($dashboardAnalysis['issues']['inconsistent'] as $issue)
                                    <li class="flex gap-2"><span class="mt-1 h-1 w-1 shrink-0 rounded-full bg-amber-500"></span><span>{{ $issue }}</span></li>
                                @endforeach
                            </ul>
                        </section>
                    @endif
                </aside>
            </div>
        </div>
    @endif
</div>
