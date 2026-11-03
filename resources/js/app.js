import './bootstrap';
import './echarts';
import { createDashboardChart } from './dashboard-chart';

window.DataCanvas = window.DataCanvas || {};
window.DataCanvas.createChart = createDashboardChart;
window.DataCanvasChart = createDashboardChart;
