<script setup lang="ts">
import { BarElement, CategoryScale, Chart as ChartJS, Legend, LinearScale, Title, Tooltip } from 'chart.js';
import { Bar } from 'vue-chartjs';
import ChartDataLabels from 'chartjs-plugin-datalabels';

// Register Chart.js components
ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, ChartDataLabels);

defineProps({
    chartData: {
        type: Object as () => any,
        required: true,
    },
    chartOptions: {
        type: Object as () => any,
        required: true,
    },
});
</script>
<template>
    <div>
        <Bar :data="chartData" :options="chartOptions" />
    </div>
</template>
