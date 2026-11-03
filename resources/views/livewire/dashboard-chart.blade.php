<div class="group overflow-hidden rounded-2xl border border-line bg-surface shadow-sm transition-shadow duration-200 hover:shadow-lg hover:shadow-slate-200/70">
    @php
        $chartType = \App\Enums\ChartType::tryFrom((string) ($recommendation['chart_type'] ?? ''));
    @endphp
    <div class="flex items-start justify-between gap-3 border-b border-line px-5 py-4">
        <div class="min-w-0">
            <h3 class="truncate text-sm font-semibold text-ink" title="{{ $recommendation['title'] ?? 'Gráfico' }}">{{ $recommendation['title'] ?? 'Gráfico' }}</h3>
            <p class="mt-1 line-clamp-2 text-xs leading-5 text-muted">{{ $recommendation['justification'] ?? '' }}</p>
        </div>
        <span class="shrink-0 rounded-full border border-indigo-100 bg-indigo-50 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.1em] text-indigo-600">
            {{ $chartType?->label() ?? ($recommendation['chart_type'] ?? '') }}
        </span>
    </div>

    <div
        class="h-[290px] w-full sm:h-[330px]"
        wire:ignore
        x-data="DataCanvasChart(@js($recommendation))"
    ></div>

    @if (! empty($recommendation['metrics']))
        @php
            $metricLabels = [
                'count' => 'registros',
                'min' => 'mínimo',
                'max' => 'máximo',
                'mean' => 'média',
                'distinct' => 'categorias',
                'total' => 'preenchidos',
                'points' => 'pontos',
                'groups' => 'grupos',
                'domains' => 'domínios',
                'emails' => 'e-mails',
            ];
        @endphp
        <div class="border-t border-line bg-canvas/50 px-5 py-2.5">
            <div class="flex flex-wrap gap-x-4 gap-y-1.5 text-[11px] text-muted">
                @foreach ($recommendation['metrics'] as $key => $value)
                    @php
                        $metricLabel = $metricLabels[$key] ?? str_replace('_', ' ', (string) $key);
                        $formattedValue = is_numeric($value)
                            ? number_format((float) $value, floor((float) $value) !== (float) $value ? 2 : 0, ',', '.')
                            : $value;
                    @endphp
                    <span class="inline-flex items-baseline gap-1">
                        <span class="font-semibold text-ink">{{ $formattedValue }}</span>
                        <span>{{ $metricLabel }}</span>
                    </span>
                @endforeach
            </div>
        </div>
    @endif
</div>
