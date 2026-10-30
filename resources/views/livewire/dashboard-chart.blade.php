<div class="rounded-xl border border-line bg-surface overflow-hidden">
    <div class="flex items-start justify-between gap-3 border-b border-line px-4 py-3 sm:px-5">
        <div class="min-w-0">
            <h3 class="truncate text-sm font-semibold">{{ $recommendation['title'] ?? 'Gráfico' }}</h3>
            <p class="mt-1 text-xs text-muted line-clamp-2">{{ $recommendation['justification'] ?? '' }}</p>
        </div>
        <span class="shrink-0 rounded bg-brand-soft px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-brand">
            {{ $recommendation['chart_type'] ?? '' }}
        </span>
    </div>
    <div
        class="w-full h-[280px] sm:h-[320px]"
        wire:ignore
        x-data="{
            chart: null,
            init() {
                this.renderChart();
                window.addEventListener('resize', () => {
                    if (this.chart) this.chart.resize();
                });
            },
            async renderChart() {
                const echarts = await window.DataCanvas.getECharts();
                this.chart = echarts.init(this.$el);
                const option = this.buildOption();
                this.chart.setOption(option);
            },
            buildOption() {
                const rec = @js($recommendation);
                const type = rec.chart_type;
                const data = rec.aggregated_data || [];
                
                if (type === 'pie') {
                    const seriesData = data.map(d => ({ name: d.value || d.category || d.label || '', value: d.count || d.value || 0 }));
                    return {
                        tooltip: { trigger: 'item' },
                        legend: { orient: 'horizontal', bottom: 0, type: 'scroll' },
                        series: [{
                            type: 'pie',
                            radius: ['40%', '70%'],
                            avoidLabelOverlap: true,
                            data: seriesData,
                            label: { formatter: '{b}: {c}' }
                        }]
                    };
                } else if (type === 'histogram' || type === 'bar') {
                    if (type === 'histogram') {
                        const xData = data.map(d => d.bin || '');
                        const yData = data.map(d => d.count || 0);
                        return {
                            tooltip: { trigger: 'axis' },
                            grid: { left: '3%', right: '4%', bottom: '3%', containLabel: true },
                            xAxis: { type: 'category', data: xData, axisLabel: { rotate: 30 } },
                            yAxis: { type: 'value' },
                            series: [{ type: 'bar', data: yData }]
                        };
                    } else {
                        const xData = data.map(d => d.category || d.value || d.label || d.date || '');
                        const yData = data.map(d => d.value || d.count || 0);
                        return {
                            tooltip: { trigger: 'axis' },
                            grid: { left: '3%', right: '4%', bottom: '3%', containLabel: true },
                            xAxis: { type: 'category', data: xData, axisLabel: { rotate: 30 } },
                            yAxis: { type: 'value' },
                            series: [{ type: 'bar', data: yData }]
                        };
                    }
                } else if (type === 'line' || type === 'area') {
                    const xData = data.map(d => d.date || '');
                    const yData = data.map(d => d.value || 0);
                    return {
                        tooltip: { trigger: 'axis' },
                        grid: { left: '3%', right: '4%', bottom: '3%', containLabel: true },
                        xAxis: { type: 'category', data: xData },
                        yAxis: { type: 'value' },
                        series: [{ type: 'line', smooth: true, areaStyle: type === 'area' ? {} : undefined, data: yData }]
                    };
                } else if (type === 'scatter') {
                    const points = data.map(d => [d.x, d.y]);
                    return {
                        tooltip: { trigger: 'item' },
                        grid: { left: '3%', right: '4%', bottom: '3%', containLabel: true },
                        xAxis: { type: 'value' },
                        yAxis: { type: 'value' },
                        series: [{ type: 'scatter', symbolSize: 10, data: points }]
                    };
                }
                return {};
            }
        }"
        x-init="init()"
    ></div>
    @if (! empty($recommendation['metrics']))
        <div class="border-t border-line px-4 py-2 sm:px-5">
            <div class="flex flex-wrap gap-3 text-xs text-muted">
                @foreach ($recommendation['metrics'] as $key => $value)
                    <span><span class="font-medium text-ink">{{ is_numeric($value) ? number_format($value, is_float($value) ? 2 : 0, ',', '.') : $value }}</span> {{ str_replace('_', ' ', $key) }}</span>
                @endforeach
            </div>
        </div>
    @endif
</div>
