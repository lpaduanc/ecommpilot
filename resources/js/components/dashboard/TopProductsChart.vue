<script setup>
import { ref, watch } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

const props = defineProps({
    data: { type: Array, default: () => [] },
});

const chartOptions = ref({
    chart: {
        type: 'bar',
        height: 300,
        toolbar: { show: false },
        fontFamily: 'DM Sans, sans-serif',
    },
    plotOptions: {
        bar: {
            horizontal: true,
            barHeight: '60%',
            borderRadius: 6,
        },
    },
    dataLabels: {
        enabled: true,
        formatter: (val) => val,
        style: {
            fontSize: '12px',
            colors: ['#fff'],
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
    },
    yaxis: {
        labels: {
            style: {
                colors: '#374151',
                fontSize: '12px',
            },
            maxWidth: 200,
        },
    },
    grid: {
        borderColor: '#f3f4f6',
        xaxis: { lines: { show: true } },
        yaxis: { lines: { show: false } },
    },
    tooltip: {
        y: {
            title: {
                formatter: () => 'Vendidos:',
            },
        },
    },
});

const series = ref([{ name: 'Quantidade', data: [] }]);

watch(() => props.data, (newData) => {
    if (newData && newData.length > 0) {
        const top10 = newData.slice(0, 10);
        chartOptions.value.xaxis.categories = top10.map(item => 
            item.name.length > 30 ? item.name.substring(0, 30) + '...' : item.name
        );
        series.value = [{ name: 'Quantidade', data: top10.map(item => item.quantity_sold) }];
    }
}, { immediate: true });
</script>

<template>
    <div>
        <VueApexCharts
            v-if="data.length > 0"
            type="bar"
            height="300"
            :options="chartOptions"
            :series="series"
        />
        <div v-else class="h-[300px] flex items-center justify-center text-gray-400">
            Sem dados para exibir
        </div>
    </div>
</template>

