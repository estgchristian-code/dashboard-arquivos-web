let echartsPromise = null;

/**
 * Carrega o Apache ECharts sob demanda, apenas quando um gráfico for criado.
 * Mantém o carregamento inicial da página leve.
 *
 * @returns {Promise<import('echarts/core')>}
 */
export function getECharts() {
    if (! echartsPromise) {
        echartsPromise = import('./echarts-runtime').then(({ default: echarts }) => {
            window.Oqentra = window.Oqentra || {};
            window.Oqentra.echarts = echarts;

            return echarts;
        });
    }

    return echartsPromise;
}

window.Oqentra = window.Oqentra || {};
window.Oqentra.getECharts = getECharts;
