<script setup>
import { ref, watch } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

const props = defineProps({
    data: { type: Array, default: () => [] },
});

// Payment status colors and labels (used for order status chart)
const statusColors = {
    // Payment statuses
    pending: '#fbbf24',
    paid: '#10b981',
    refunded: '#8b5cf6',
    voided: '#ef4444',
    failed: '#dc2626',
    // Order statuses (fallback)
    shipped: '#0ea5e9',
    delivered: '#059669',
    cancelled: '#f97316',
};

const statusLabels = {
    // Payment statuses
    pending: 'Pendente',
    paid: 'Pago',
    refunded: 'Reembolsado',
    voided: 'Recusado',
    failed: 'Falhou',
    // Order statuses (fallback)
    shipped: 'Enviado',
    delivered: 'Entregue',
    cancelled: 'Cancelado',
};

const chartOptions = ref({
    chart: {
        type: 'donut',
        fontFamily: 'DM Sans, sans-serif',
    },
    labels: [],
    colors: [],
    dataLabels: {
        enabled: false,
    },
    legend: {
        position: 'bottom',
        fontFamily: 'DM Sans, sans-serif',
        labels: {
            colors: '#6b7280',
        },
    },
    plotOptions: {
        pie: {
            donut: {
                size: '70%',
                labels: {
                    show: true,
                    total: {
                        show: true,
                        label: 'Total',
                        fontFamily: 'DM Sans, sans-serif',
                        fontWeight: 600,
                        color: '#111827',
                    },
                },
            },
        },
    },
    responsive: [{
        breakpoint: 480,
        options: {
            chart: {
                width: 200,
            },
            legend: {
                position: 'bottom',
            },
        },
    }],
});

const series = ref([]);

watch(() => props.data, (newData) => {
    if (newData && newData.length > 0) {
        chartOptions.value.labels = newData.map(item => statusLabels[item.status] || item.status);
        chartOptions.value.colors = newData.map(item => statusColors[item.status] || '#9ca3af');
        series.value = newData.map(item => item.count);
    }
}, { immediate: true });
</script>

<template>
    <div>
        <VueApexCharts
            v-if="data.length > 0"
            type="donut"
            height="300"
            :options="chartOptions"
            :series="series"
        />
        <div v-else class="h-[300px] flex items-center justify-center text-gray-400">
            Sem dados para exibir
        </div>
    </div>
</template>

