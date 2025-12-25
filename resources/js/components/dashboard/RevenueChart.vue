<script setup>
import { ref, watch, onMounted } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

const props = defineProps({
    data: { type: Array, default: () => [] },
});

const chartOptions = ref({
    chart: {
        type: 'area',
        height: 300,
        toolbar: { show: false },
        zoom: { enabled: false },
        fontFamily: 'DM Sans, sans-serif',
    },
    dataLabels: { enabled: false },
    stroke: {
        curve: 'smooth',
        width: 3,
    },
    fill: {
        type: 'gradient',
        gradient: {
            shadeIntensity: 1,
            opacityFrom: 0.4,
            opacityTo: 0.1,
            stops: [0, 90, 100],
        },
    },
    colors: ['#0c87f7'],
    xaxis: {
        categories: [],
        labels: {
            style: {
                colors: '#9ca3af',
                fontSize: '12px',
            },
        },
        axisBorder: { show: false },
        axisTicks: { show: false },
    },
    yaxis: {
        labels: {
            style: {
                colors: '#9ca3af',
                fontSize: '12px',
            },
            formatter: (value) => {
                return new Intl.NumberFormat('pt-BR', {
                    style: 'currency',
                    currency: 'BRL',
                    notation: 'compact',
                }).format(value);
            },
        },
    },
    grid: {
        borderColor: '#f3f4f6',
        strokeDashArray: 4,
    },
    tooltip: {
        y: {
            formatter: (value) => {
                return new Intl.NumberFormat('pt-BR', {
                    style: 'currency',
                    currency: 'BRL',
                }).format(value);
            },
        },
    },
});

const series = ref([{ name: 'Receita', data: [] }]);

watch(() => props.data, (newData) => {
    if (newData && newData.length > 0) {
        chartOptions.value.xaxis.categories = newData.map(item => item.date);
        series.value = [{ name: 'Receita', data: newData.map(item => item.value) }];
    }
}, { immediate: true });
</script>

<template>
    <div>
        <VueApexCharts
            v-if="data.length > 0"
            type="area"
            height="300"
            :options="chartOptions"
            :series="series"
        />
        <div v-else class="h-[300px] flex items-center justify-center text-gray-400">
            Sem dados para exibir
        </div>
    </div>
</template>

