<template>
    <div class="chart-container">
        <canvas ref="chartCanvas"></canvas>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted, watch, onBeforeUnmount } from 'vue';
import {
    Chart,
    LineController,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Title,
    Tooltip,
    Legend,
    Filler
} from 'chart.js';

// Register Chart.js components
Chart.register(LineController, CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend, Filler);

interface ChartData {
    labels: string[];
    datasets: {
        label: string;
        data: number[];
        borderColor: string;
        backgroundColor: string;
        tension?: number;
        fill?: boolean;
        borderWidth?: number;
    }[];
}

interface ChartOptions {
    responsive: boolean;
    plugins: {
        legend: {
            display: boolean;
            position: string;
        };
        title: {
            display: boolean;
            text: string;
        };
    };
}

const props = defineProps<{
    chartData: ChartData;
    chartOptions: ChartOptions;
}>();

const chartCanvas = ref<HTMLCanvasElement | null>(null);
let chartInstance: Chart | null = null;

const initChart = () => {
    if (!chartCanvas.value) return;

    // Destroy existing chart
    if (chartInstance) {
        chartInstance.destroy();
    }

    chartInstance = new Chart(chartCanvas.value, {
        type: 'line',
        data: {
            labels: props.chartData.labels,
            datasets: props.chartData.datasets.map(dataset => ({
                ...dataset,
                tension: dataset.tension || 0.4,
                fill: true,
                borderWidth: dataset.borderWidth || 2,
                pointBackgroundColor: dataset.borderColor,
                pointBorderColor: '#ffffff',
                pointRadius: 4,
                pointHoverRadius: 6,
            }))
        },
        options: {
            responsive: props.chartOptions.responsive,
            maintainAspectRatio: false,
            plugins: {
                legend: props.chartOptions.plugins.legend,
                title: props.chartOptions.plugins.title,
                tooltip: {
                    mode: 'index',
                    intersect: false,
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.1)',
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
};

onMounted(() => {
    initChart();
});

watch(() => [props.chartData, props.chartOptions], () => {
    initChart();
}, { deep: true });

onBeforeUnmount(() => {
    if (chartInstance) {
        chartInstance.destroy();
    }
});
</script>

<style scoped>
.chart-container {
    position: relative;
    height: 300px;
    width: 100%;
}
</style>
