<!-- @/components/chartcomponents/RadialChart.vue -->
<template>
    <div class="chart-container">
        <canvas ref="chartCanvas"></canvas>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted, watch, onBeforeUnmount } from 'vue';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

const props = defineProps<{
    chartData: any;
    chartOptions: any;
}>();

const chartCanvas = ref<HTMLCanvasElement | null>(null);
let chartInstance: Chart | null = null;

const renderChart = () => {
    if (chartInstance) {
        chartInstance.destroy();
    }

    if (chartCanvas.value && props.chartData) {
        chartInstance = new Chart(chartCanvas.value, {
            type: 'radar',
            data: props.chartData,
            options: props.chartOptions
        });
    }
};

onMounted(() => {
    renderChart();
});

watch(() => [props.chartData, props.chartOptions], () => {
    renderChart();
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
