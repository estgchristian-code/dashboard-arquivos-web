import * as echarts from 'echarts/core';
import { BarChart, LineChart, PieChart, ScatterChart } from 'echarts/charts';
import {
    DatasetComponent,
    GridComponent,
    LegendComponent,
    TitleComponent,
    TooltipComponent,
} from 'echarts/components';
import { CanvasRenderer } from 'echarts/renderers';

echarts.use([
    BarChart,
    LineChart,
    PieChart,
    ScatterChart,
    DatasetComponent,
    GridComponent,
    LegendComponent,
    TitleComponent,
    TooltipComponent,
    CanvasRenderer,
]);

export default echarts;
