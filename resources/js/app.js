import './bootstrap';
import './echarts';
import { createDashboardChart } from './dashboard-chart';

window.Oqentra = window.Oqentra || {};
window.Oqentra.createChart = createDashboardChart;
window.OqentraChart = createDashboardChart;
