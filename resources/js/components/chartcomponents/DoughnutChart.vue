<script setup lang="ts">
import { ArcElement, Chart as ChartJS, Legend, Tooltip } from 'chart.js';
import { Doughnut } from 'vue-chartjs'
import ChartDataLabels from 'chartjs-plugin-datalabels';

// Register Chart.js components
ChartJS.register(ArcElement, Tooltip, Legend, ChartDataLabels);

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
        <Doughnut :data="chartData" :options="chartOptions" />
    </div>
</template>
