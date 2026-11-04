const PALETTE = [
    '#6366f1',
    '#8b5cf6',
    '#06b6d4',
    '#14b8a6',
    '#f59e0b',
    '#ef4444',
    '#ec4899',
    '#84cc16',
    '#0ea5e9',
    '#a855f7',
];

const AXIS_TEXT = { color: '#64748b', fontSize: 11 };
const AXIS_LINE = { show: false };
const AXIS_TICK = { show: false };
const SPLIT_LINE = { lineStyle: { color: '#eef2f7' } };
const GRID = { left: 18, right: 22, top: 28, bottom: 18, containLabel: true };

const TOOLTIP = {
    backgroundColor: 'rgba(15, 23, 42, .95)',
    borderWidth: 0,
    padding: [10, 12],
    textStyle: { color: '#f8fafc', fontSize: 12 },
    extraCssText: 'border-radius: 12px; box-shadow: 0 18px 40px rgba(15, 23, 42, .28);',
};

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (character) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
    }[character]));
}

function formatNumber(value) {
    const number = Number(value);

    if (! Number.isFinite(number)) {
        return String(value ?? '');
    }

    return new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 2 }).format(number);
}

function axisTooltip() {
    return {
        ...TOOLTIP,
        trigger: 'axis',
        axisPointer: { type: 'shadow', shadowStyle: { color: 'rgba(99, 102, 241, .08)' } },
        formatter: (params) => {
            const items = Array.isArray(params) ? params : [params];
            const title = escapeHtml(items[0]?.axisValueLabel ?? items[0]?.name ?? '');
            const rows = items.map((item) => `
                <div style="display:flex;align-items:center;justify-content:space-between;gap:18px;margin-top:4px">
                    <span>${item.marker ?? ''}${escapeHtml(item.seriesName ?? '')}</span>
                    <span style="font-weight:600">${formatNumber(item.value)}</span>
                </div>
            `).join('');

            return `<div style="font-weight:600;margin-bottom:2px">${title}</div>${rows}`;
        },
    };
}

function pieOption(data) {
    const seriesData = data.map((item) => ({
        name: item.name ?? item.label ?? item.value ?? item.category ?? item.date ?? '',
        value: Number(item.count ?? item.value ?? 0) || 0,
    }));

    return {
        tooltip: {
            ...TOOLTIP,
            trigger: 'item',
            formatter: (params) => `
                <div style="font-weight:600;margin-bottom:3px">${escapeHtml(params.name)}</div>
                <div>${formatNumber(params.value)} registros · <span style="font-weight:600">${params.percent}%</span></div>
            `,
        },
        legend: {
            type: 'scroll',
            bottom: 2,
            left: 'center',
            icon: 'circle',
            itemWidth: 8,
            itemHeight: 8,
            itemGap: 14,
            textStyle: { color: '#475569', fontSize: 11 },
        },
        series: [{
            type: 'pie',
            radius: ['48%', '73%'],
            center: ['50%', '45%'],
            avoidLabelOverlap: true,
            padAngle: 2,
            itemStyle: {
                borderRadius: 8,
                borderColor: '#ffffff',
                borderWidth: 2,
                shadowBlur: 12,
                shadowColor: 'rgba(15, 23, 42, .08)',
            },
            label: { show: false },
            labelLine: { show: false },
            emphasis: {
                scale: true,
                scaleSize: 6,
                label: {
                    show: true,
                    formatter: '{b}\n{d}%',
                    fontSize: 11,
                    fontWeight: 600,
                    color: '#334155',
                    lineHeight: 16,
                },
            },
            data: seriesData,
        }],
    };
}

function barOption(data, recommendation) {
    const isHistogram = data.some((item) => item.bin !== undefined);
    const isAggregation = ! isHistogram && data.some((item) => item.category !== undefined);

    const labels = data.map((item) => {
        if (isHistogram) return item.bin ?? '';
        if (isAggregation) return item.category ?? '';
        if (typeof item.value === 'string' && item.count !== undefined) return item.value;

        return item.label ?? item.date ?? (typeof item.value === 'string' ? item.value : '') ?? '';
    });

    const values = data.map((item) => {
        if (isHistogram) return Number(item.count ?? item.value ?? 0);
        if (isAggregation) return Number(item.value ?? item.count ?? 0);
        if (typeof item.count === 'number') return item.count;

        return Number(item.value ?? item.count ?? 0);
    });

    const longLabels = labels.length > 7 || labels.some((label) => String(label).length > 18);
    const horizontal = ! isHistogram && longLabels;
    const gradient = horizontal
        ? { type: 'linear', x: 0, y: 0, x2: 1, y2: 0, colorStops: [{ offset: 0, color: '#6366f1' }, { offset: 1, color: '#8b5cf6' }] }
        : { type: 'linear', x: 0, y: 0, x2: 0, y2: 1, colorStops: [{ offset: 0, color: '#818cf8' }, { offset: 1, color: '#6366f1' }] };

    const categoryAxis = {
        type: 'category',
        data: labels,
        axisLine: AXIS_LINE,
        axisTick: AXIS_TICK,
        axisLabel: {
            ...AXIS_TEXT,
            interval: 0,
            hideOverlap: true,
            width: horizontal ? 132 : undefined,
            overflow: 'truncate',
            rotate: ! horizontal && labels.length > 6 ? 28 : 0,
            formatter: (value) => String(value).length > (horizontal ? 22 : 16)
                ? String(value).slice(0, horizontal ? 20 : 14) + '…'
                : value,
        },
    };

    const valueAxis = {
        type: 'value',
        axisLine: AXIS_LINE,
        axisTick: AXIS_TICK,
        axisLabel: { ...AXIS_TEXT, formatter: (value) => formatNumber(value) },
        splitLine: SPLIT_LINE,
    };

    return {
        tooltip: axisTooltip(),
        grid: isHistogram ? { ...GRID, bottom: 22 } : GRID,
        xAxis: horizontal ? valueAxis : categoryAxis,
        yAxis: horizontal ? { ...categoryAxis, inverse: true } : valueAxis,
        series: [{
            type: 'bar',
            name: isAggregation ? (recommendation.columns?.[1] ?? 'Valor') : 'Registros',
            data: values,
            barMaxWidth: horizontal ? 16 : 36,
            showBackground: true,
            backgroundStyle: {
                color: '#f4f6fb',
                borderRadius: horizontal ? [0, 8, 8, 0] : [8, 8, 0, 0],
            },
            itemStyle: {
                color: gradient,
                borderRadius: horizontal ? [0, 8, 8, 0] : [8, 8, 0, 0],
            },
            emphasis: { itemStyle: { shadowBlur: 16, shadowColor: 'rgba(99, 102, 241, .35)' } },
        }],
    };
}

function lineOption(data, recommendation, area) {
    const labels = data.map((item) => item.date ?? item.label ?? item.category ?? '');
    const values = data.map((item) => Number(item.value ?? item.count ?? 0));

    return {
        tooltip: {
            ...axisTooltip(),
            axisPointer: { type: 'line', lineStyle: { color: 'rgba(99, 102, 241, .35)', width: 1 } },
        },
        grid: GRID,
        xAxis: {
            type: 'category',
            boundaryGap: false,
            data: labels,
            axisLine: { lineStyle: { color: '#e2e8f0' } },
            axisTick: AXIS_TICK,
            axisLabel: {
                ...AXIS_TEXT,
                hideOverlap: true,
                formatter: (value) => {
                    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(value));

                    return match ? `${match[3]}/${match[2]}` : value;
                },
            },
        },
        yAxis: {
            type: 'value',
            axisLine: AXIS_LINE,
            axisTick: AXIS_TICK,
            axisLabel: { ...AXIS_TEXT, formatter: (value) => formatNumber(value) },
            splitLine: SPLIT_LINE,
        },
        series: [{
            type: 'line',
            name: recommendation.columns?.[1] ?? 'Valor',
            data: values,
            smooth: true,
            symbol: 'circle',
            symbolSize: 7,
            showSymbol: values.length <= 40,
            lineStyle: { width: 3, color: '#6366f1' },
            itemStyle: { color: '#6366f1', borderColor: '#ffffff', borderWidth: 2 },
            areaStyle: area ? {
                opacity: 1,
                color: {
                    type: 'linear',
                    x: 0,
                    y: 0,
                    x2: 0,
                    y2: 1,
                    colorStops: [
                        { offset: 0, color: 'rgba(99, 102, 241, .34)' },
                        { offset: 1, color: 'rgba(99, 102, 241, .02)' },
                    ],
                },
            } : undefined,
            emphasis: { focus: 'series' },
        }],
    };
}

function scatterOption(data, recommendation) {
    const points = data.map((item) => [Number(item.x ?? 0), Number(item.y ?? 0)]);

    return {
        tooltip: {
            ...TOOLTIP,
            trigger: 'item',
            formatter: (params) => `
                <div style="font-weight:600;margin-bottom:3px">${escapeHtml(recommendation.title ?? 'Ponto')}</div>
                <div>${escapeHtml(recommendation.columns?.[0] ?? 'X')}: <span style="font-weight:600">${formatNumber(params.value?.[0])}</span></div>
                <div>${escapeHtml(recommendation.columns?.[1] ?? 'Y')}: <span style="font-weight:600">${formatNumber(params.value?.[1])}</span></div>
            `,
        },
        grid: GRID,
        xAxis: {
            type: 'value',
            name: recommendation.columns?.[0] ?? '',
            nameLocation: 'middle',
            nameGap: 28,
            nameTextStyle: { color: '#64748b', fontSize: 11 },
            axisLine: { lineStyle: { color: '#e2e8f0' } },
            axisTick: AXIS_TICK,
            axisLabel: { ...AXIS_TEXT, formatter: (value) => formatNumber(value) },
            splitLine: SPLIT_LINE,
        },
        yAxis: {
            type: 'value',
            name: recommendation.columns?.[1] ?? '',
            nameLocation: 'middle',
            nameGap: 36,
            nameTextStyle: { color: '#64748b', fontSize: 11 },
            axisLine: AXIS_LINE,
            axisTick: AXIS_TICK,
            axisLabel: { ...AXIS_TEXT, formatter: (value) => formatNumber(value) },
            splitLine: SPLIT_LINE,
        },
        series: [{
            type: 'scatter',
            name: recommendation.title ?? 'Dispersão',
            data: points,
            symbolSize: 10,
            itemStyle: {
                color: 'rgba(139, 92, 246, .78)',
                borderColor: '#ffffff',
                borderWidth: 1.5,
                shadowBlur: 8,
                shadowColor: 'rgba(139, 92, 246, .25)',
            },
            emphasis: { scale: 1.5, itemStyle: { color: '#6d28d9' } },
        }],
    };
}

export function createDashboardChart(recommendation) {
    return {
        chart: null,
        resizeHandler: null,
        recommendation,

        init() {
            this.renderChart();
            this.resizeHandler = () => {
                if (this.chart) {
                    this.chart.resize();
                }
            };
            window.addEventListener('resize', this.resizeHandler);
        },

        destroy() {
            window.removeEventListener('resize', this.resizeHandler);

            if (this.chart) {
                this.chart.dispose();
                this.chart = null;
            }
        },

        async renderChart() {
            const echarts = await window.Oqentra.getECharts();
            this.chart = echarts.init(this.$el, null, { renderer: 'canvas' });
            this.chart.setOption(this.buildOption(), true);
        },

        buildOption() {
            const rec = this.recommendation ?? {};
            const type = rec.chart_type;
            const data = Array.isArray(rec.aggregated_data) ? rec.aggregated_data : [];

            const base = {
                color: PALETTE,
                textStyle: {
                    fontFamily: 'ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif',
                },
                animationDuration: 650,
                animationDurationUpdate: 400,
                animationEasing: 'cubicOut',
            };

            if (type === 'pie') {
                return { ...base, ...pieOption(data) };
            }

            if (type === 'histogram' || type === 'bar') {
                return { ...base, ...barOption(data, rec) };
            }

            if (type === 'line' || type === 'area') {
                return { ...base, ...lineOption(data, rec, type === 'area') };
            }

            if (type === 'scatter') {
                return { ...base, ...scatterOption(data, rec) };
            }

            return base;
        },
    };
}
