<script setup>
import { ref, onMounted } from 'vue';
import api from '../services/api';
import BaseCard from '../components/common/BaseCard.vue';
import BaseButton from '../components/common/BaseButton.vue';
import BaseModal from '../components/common/BaseModal.vue';
import LoadingSpinner from '../components/common/LoadingSpinner.vue';
import {
    CurrencyDollarIcon,
    MagnifyingGlassIcon,
    FunnelIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    EyeIcon,
    XMarkIcon,
    TruckIcon,
    DocumentTextIcon,
    MapPinIcon,
    UserIcon,
    PhoneIcon,
    EnvelopeIcon,
    SparklesIcon,
} from '@heroicons/vue/24/outline';

const orders = ref([]);
const isLoading = ref(false);
const searchQuery = ref('');
const statusFilter = ref('');
const currentPage = ref(1);
const totalPages = ref(1);
const totalItems = ref(0);

// Order detail modal
const showDetailModal = ref(false);
const selectedOrder = ref(null);

const statusOptions = [
    { value: '', label: 'Todos os Status' },
    { value: 'pending', label: 'Pendente' },
    { value: 'paid', label: 'Pago' },
    { value: 'shipped', label: 'Enviado' },
    { value: 'delivered', label: 'Entregue' },
    { value: 'cancelled', label: 'Cancelado' },
];

async function fetchOrders() {
    isLoading.value = true;
    try {
        const response = await api.get('/orders', {
            params: {
                search: searchQuery.value,
                status: statusFilter.value,
                page: currentPage.value,
                per_page: 20,
            },
        });
        orders.value = response.data.data;
        totalPages.value = response.data.last_page;
        totalItems.value = response.data.total;
    } catch {
        orders.value = [];
    } finally {
        isLoading.value = false;
    }
}

function handleSearch() {
    currentPage.value = 1;
    fetchOrders();
}

function goToPage(page) {
    if (page < 1 || page > totalPages.value) return;
    currentPage.value = page;
    fetchOrders();
}

function formatCurrency(value) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(value);
}

function formatDate(date) {
    return new Date(date).toLocaleDateString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function getStatusConfig(status) {
    const configs = {
        pending: { label: 'Pendente', color: 'warning' },
        paid: { label: 'Pago', color: 'success' },
        shipped: { label: 'Enviado', color: 'primary' },
        delivered: { label: 'Entregue', color: 'success' },
        cancelled: { label: 'Cancelado', color: 'danger' },
    };
    return configs[status] || { label: status, color: 'gray' };
}

function getPaymentStatusConfig(status) {
    const configs = {
        pending: { label: 'Aguardando', color: 'warning' },
        paid: { label: 'Pago', color: 'success' },
        refunded: { label: 'Reembolsado', color: 'gray' },
        failed: { label: 'Falhou', color: 'danger' },
    };
    return configs[status] || { label: status, color: 'gray' };
}

function getPaymentMethodLabel(method) {
    const labels = {
        pix: 'PIX',
        credit_card: 'Cartão de Crédito',
        debit_card: 'Cartão de Débito',
        boleto: 'Boleto',
    };
    return labels[method] || method;
}

function viewOrderDetail(order) {
    selectedOrder.value = order;
    showDetailModal.value = true;
}

onMounted(() => {
    fetchOrders();
});
</script>

<template>
    <div class="min-h-screen -m-8 -mt-8">
        <!-- Hero Header with Gradient -->
        <div class="relative overflow-hidden bg-gradient-to-br from-slate-900 via-primary-950 to-secondary-950 px-8 py-12">
            <!-- Animated Background Elements -->
            <div class="absolute inset-0 overflow-hidden">
                <div class="absolute -top-40 -right-40 w-80 h-80 bg-primary-500/20 rounded-full blur-3xl animate-pulse-soft"></div>
                <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-secondary-500/20 rounded-full blur-3xl animate-pulse-soft" style="animation-delay: 1s;"></div>
                <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-96 h-96 bg-accent-500/10 rounded-full blur-3xl"></div>
                <!-- Grid Pattern -->
                <div class="absolute inset-0" style="background-image: radial-gradient(rgba(255,255,255,0.03) 1px, transparent 1px); background-size: 40px 40px;"></div>
            </div>
            
            <div class="relative z-10 max-w-7xl mx-auto">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                    <div class="space-y-4">
                        <div class="flex items-center gap-3">
                            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-primary-400 to-secondary-500 flex items-center justify-center shadow-lg shadow-primary-500/30">
                                <CurrencyDollarIcon class="w-7 h-7 text-white" />
                            </div>
                            <div>
                                <h1 class="text-3xl lg:text-4xl font-display font-bold text-white">
                                    Vendas
                                </h1>
                                <p class="text-primary-200/80 text-sm lg:text-base">
                                    {{ totalItems }} pedidos sincronizados
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Search & Filters -->
                    <div class="flex items-center gap-3">
                        <div class="relative flex-1 max-w-md">
                            <MagnifyingGlassIcon class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                            <input
                                v-model="searchQuery"
                                @keyup.enter="handleSearch"
                                type="text"
                                placeholder="Buscar por número ou cliente..."
                                class="w-full pl-12 pr-4 py-3 rounded-xl bg-white/10 backdrop-blur-sm border border-white/20 text-white placeholder-white/60 focus:bg-white/20 focus:border-white/30 focus:ring-2 focus:ring-primary-500/50 focus:outline-none transition-all"
                            />
                        </div>
                        <select
                            v-model="statusFilter"
                            @change="handleSearch"
                            class="px-4 py-3 rounded-xl bg-white/10 backdrop-blur-sm border border-white/20 text-white focus:bg-white/20 focus:border-white/30 focus:ring-2 focus:ring-primary-500/50 focus:outline-none transition-all"
                        >
                            <option value="" class="text-gray-900">Todos os Status</option>
                            <option v-for="option in statusOptions.slice(1)" :key="option.value" :value="option.value" class="text-gray-900">
                                {{ option.label }}
                            </option>
                        </select>
                        <button
                            @click="handleSearch"
                            class="px-6 py-3 rounded-xl bg-white/10 backdrop-blur-sm border border-white/20 text-white font-medium hover:bg-white/20 transition-all"
                        >
                            <FunnelIcon class="w-5 h-5" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="px-8 py-8 bg-gradient-to-b from-gray-100 to-gray-50 min-h-[calc(100vh-200px)]">
            <div class="max-w-7xl mx-auto">
                <!-- Orders Table -->
                <BaseCard padding="none" class="overflow-hidden animate-fade-in">
                    <!-- Loading -->
                    <div v-if="isLoading" class="flex items-center justify-center py-20">
                        <div class="relative">
                            <div class="w-16 h-16 rounded-full bg-gradient-to-r from-primary-500 to-secondary-500 animate-pulse"></div>
                            <LoadingSpinner size="lg" class="absolute inset-0 m-auto text-white" />
                        </div>
                    </div>

                    <!-- Table -->
                    <div v-else-if="orders.length > 0" class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200">
                                <tr>
                                    <th class="text-left px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                        Pedido
                                    </th>
                                    <th class="text-left px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                        Cliente
                                    </th>
                                    <th class="text-left px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th class="text-left px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                        Pagamento
                                    </th>
                                    <th class="text-left px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                        Total
                                    </th>
                                    <th class="text-left px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                        Data
                                    </th>
                                    <th class="text-left px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                        Ações
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr
                                    v-for="(order, index) in orders"
                                    :key="order.id"
                                    :class="[
                                        'hover:bg-gradient-to-r hover:from-primary-50/50 hover:to-transparent transition-all duration-200',
                                        'animate-slide-up'
                                    ]"
                                    :style="{ animationDelay: `${index * 30}ms` }"
                                >
                                    <td class="px-6 py-4">
                                        <span class="font-medium text-gray-900">{{ order.order_number }}</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div>
                                            <p class="font-medium text-gray-900">{{ order.customer_name }}</p>
                                            <p class="text-sm text-gray-500">{{ order.customer_email }}</p>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span :class="['badge', `badge-${getStatusConfig(order.status).color}`]">
                                            {{ getStatusConfig(order.status).label }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span :class="['badge', `badge-${getPaymentStatusConfig(order.payment_status).color}`]">
                                            {{ getPaymentStatusConfig(order.payment_status).label }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="font-semibold text-gray-900">{{ formatCurrency(order.total) }}</span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        {{ formatDate(order.external_created_at) }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <BaseButton variant="ghost" size="sm" @click="viewOrderDetail(order)" title="Ver detalhes">
                                            <EyeIcon class="w-4 h-4" />
                                        </BaseButton>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Empty State -->
                    <div v-else class="text-center py-20 animate-fade-in">
                        <div class="relative inline-block mb-6">
                            <div class="w-32 h-32 rounded-3xl bg-gradient-to-br from-primary-100 to-secondary-100 flex items-center justify-center">
                                <CurrencyDollarIcon class="w-16 h-16 text-primary-400" />
                            </div>
                            <div class="absolute -bottom-2 -right-2 w-8 h-8 rounded-full bg-accent-400 flex items-center justify-center">
                                <SparklesIcon class="w-4 h-4 text-white" />
                            </div>
                        </div>
                        <h3 class="text-2xl font-display font-bold text-gray-900 mb-3">
                            Nenhum pedido encontrado
                        </h3>
                        <p class="text-gray-500">
                            {{ searchQuery || statusFilter ? 'Tente filtros diferentes' : 'Conecte sua loja para sincronizar pedidos' }}
                        </p>
                    </div>

                    <!-- Pagination -->
                    <div v-if="totalPages > 1" class="flex items-center justify-between px-6 py-4 border-t border-gray-100 bg-gray-50/50">
                        <p class="text-sm text-gray-500">
                            Mostrando {{ (currentPage - 1) * 20 + 1 }} a {{ Math.min(currentPage * 20, totalItems) }} de {{ totalItems }}
                        </p>
                        <div class="flex items-center gap-2">
                            <BaseButton
                                variant="ghost"
                                size="sm"
                                :disabled="currentPage === 1"
                                @click="goToPage(currentPage - 1)"
                            >
                                <ChevronLeftIcon class="w-4 h-4" />
                            </BaseButton>
                            <span class="text-sm text-gray-600 px-3">
                                {{ currentPage }} / {{ totalPages }}
                            </span>
                            <BaseButton
                                variant="ghost"
                                size="sm"
                                :disabled="currentPage === totalPages"
                                @click="goToPage(currentPage + 1)"
                            >
                                <ChevronRightIcon class="w-4 h-4" />
                            </BaseButton>
                        </div>
                    </div>
                </BaseCard>
            </div>
        </div>

        <!-- Order Detail Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div 
                    v-if="showDetailModal && selectedOrder" 
                    class="fixed inset-0 z-50 flex items-center justify-center p-4"
                >
                    <!-- Backdrop -->
                    <div 
                        class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm"
                        @click="showDetailModal = false"
                    ></div>

                    <!-- Modal -->
                    <div class="relative w-full max-w-3xl max-h-[90vh] overflow-hidden bg-white rounded-3xl shadow-2xl animate-scale-in">
                        <!-- Header with Gradient -->
                        <div class="relative px-8 py-6 bg-gradient-to-r from-primary-500 to-secondary-500 overflow-hidden">
                            <!-- Background Pattern -->
                            <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(rgba(255,255,255,0.3) 1px, transparent 1px); background-size: 20px 20px;"></div>
                            
                            <!-- Close Button -->
                            <button
                                @click="showDetailModal = false"
                                class="absolute top-4 right-4 p-2 rounded-full bg-white/20 hover:bg-white/30 transition-colors"
                            >
                                <XMarkIcon class="w-5 h-5 text-white" />
                            </button>

                            <div class="relative flex items-center justify-between">
                                <div>
                                    <p class="text-white/80 text-sm mb-1">Pedido</p>
                                    <p class="text-2xl font-display font-bold text-white">{{ selectedOrder.order_number }}</p>
                                </div>
                                <span :class="['badge bg-white/20 text-white border-white/30', `badge-${getStatusConfig(selectedOrder.status).color}`]">
                                    {{ getStatusConfig(selectedOrder.status).label }}
                                </span>
                            </div>
                        </div>

                        <!-- Content -->
                        <div class="px-8 py-6 max-h-[60vh] overflow-y-auto scrollbar-thin space-y-6">
                            <!-- Customer Info -->
                            <div class="space-y-3">
                                <h4 class="font-semibold text-gray-900 flex items-center gap-2">
                                    <div class="w-8 h-8 rounded-lg bg-primary-100 flex items-center justify-center">
                                        <UserIcon class="w-4 h-4 text-primary-600" />
                                    </div>
                                    Informações do Cliente
                                </h4>
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="flex items-center gap-3 p-3 bg-gradient-to-r from-gray-50 to-transparent rounded-xl">
                                        <UserIcon class="w-5 h-5 text-gray-400" />
                                        <div>
                                            <p class="text-xs text-gray-500">Nome</p>
                                            <p class="font-medium text-gray-900">{{ selectedOrder.customer_name }}</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3 p-3 bg-gradient-to-r from-gray-50 to-transparent rounded-xl">
                                        <EnvelopeIcon class="w-5 h-5 text-gray-400" />
                                        <div>
                                            <p class="text-xs text-gray-500">E-mail</p>
                                            <p class="font-medium text-gray-900">{{ selectedOrder.customer_email }}</p>
                                        </div>
                                    </div>
                                    <div v-if="selectedOrder.customer_phone" class="flex items-center gap-3 p-3 bg-gradient-to-r from-gray-50 to-transparent rounded-xl">
                                        <PhoneIcon class="w-5 h-5 text-gray-400" />
                                        <div>
                                            <p class="text-xs text-gray-500">Telefone</p>
                                            <p class="font-medium text-gray-900">{{ selectedOrder.customer_phone }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Shipping Address -->
                            <div v-if="selectedOrder.shipping_address" class="space-y-3">
                                <h4 class="font-semibold text-gray-900 flex items-center gap-2">
                                    <div class="w-8 h-8 rounded-lg bg-success-100 flex items-center justify-center">
                                        <MapPinIcon class="w-4 h-4 text-success-600" />
                                    </div>
                                    Endereço de Entrega
                                </h4>
                                <div class="p-4 bg-gradient-to-r from-gray-50 to-transparent rounded-xl border border-gray-200">
                                    <p class="text-gray-900">
                                        {{ selectedOrder.shipping_address.street }}, {{ selectedOrder.shipping_address.number }}
                                        <span v-if="selectedOrder.shipping_address.complement"> - {{ selectedOrder.shipping_address.complement }}</span>
                                    </p>
                                    <p class="text-gray-600">
                                        {{ selectedOrder.shipping_address.neighborhood }} - {{ selectedOrder.shipping_address.city }}/{{ selectedOrder.shipping_address.state }}
                                    </p>
                                    <p class="text-gray-500">CEP: {{ selectedOrder.shipping_address.zip_code }}</p>
                                </div>
                            </div>

                            <!-- Order Items -->
                            <div class="space-y-3">
                                <h4 class="font-semibold text-gray-900 flex items-center gap-2">
                                    <div class="w-8 h-8 rounded-lg bg-accent-100 flex items-center justify-center">
                                        <DocumentTextIcon class="w-4 h-4 text-accent-600" />
                                    </div>
                                    Itens do Pedido
                                </h4>
                                <div class="border border-gray-200 rounded-xl overflow-hidden">
                                    <table class="w-full text-sm">
                                        <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
                                            <tr>
                                                <th class="text-left px-4 py-2 text-gray-500 font-medium">Produto</th>
                                                <th class="text-center px-4 py-2 text-gray-500 font-medium">Qtd</th>
                                                <th class="text-right px-4 py-2 text-gray-500 font-medium">Preço</th>
                                                <th class="text-right px-4 py-2 text-gray-500 font-medium">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100">
                                            <tr v-for="(item, index) in selectedOrder.items" :key="index" class="hover:bg-gray-50">
                                                <td class="px-4 py-3 text-gray-900">{{ item.product_name || item.name }}</td>
                                                <td class="px-4 py-3 text-center text-gray-600">{{ item.quantity }}</td>
                                                <td class="px-4 py-3 text-right text-gray-600">{{ formatCurrency(item.unit_price) }}</td>
                                                <td class="px-4 py-3 text-right font-medium text-gray-900">{{ formatCurrency(item.total) }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Order Summary -->
                            <div class="space-y-2 p-4 bg-gradient-to-r from-primary-50 to-secondary-50 rounded-xl border border-primary-100">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500">Subtotal</span>
                                    <span class="text-gray-900 font-medium">{{ formatCurrency(selectedOrder.subtotal) }}</span>
                                </div>
                                <div v-if="selectedOrder.discount > 0" class="flex justify-between text-sm">
                                    <span class="text-gray-500">Desconto</span>
                                    <span class="text-success-600 font-medium">-{{ formatCurrency(selectedOrder.discount) }}</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500">Frete</span>
                                    <span class="text-gray-900 font-medium">{{ selectedOrder.shipping > 0 ? formatCurrency(selectedOrder.shipping) : 'Grátis' }}</span>
                                </div>
                                <div class="flex justify-between text-lg font-bold pt-2 border-t border-gray-200">
                                    <span class="text-gray-900">Total</span>
                                    <span class="text-gray-900">{{ formatCurrency(selectedOrder.total) }}</span>
                                </div>
                            </div>

                            <!-- Payment Info -->
                            <div class="flex items-center justify-between p-4 bg-gradient-to-r from-gray-50 to-transparent rounded-xl border border-gray-200">
                                <div>
                                    <p class="text-sm text-gray-500">Método de Pagamento</p>
                                    <p class="font-medium text-gray-900">{{ getPaymentMethodLabel(selectedOrder.payment_method) }}</p>
                                </div>
                                <span :class="['badge', `badge-${getPaymentStatusConfig(selectedOrder.payment_status).color}`]">
                                    {{ getPaymentStatusConfig(selectedOrder.payment_status).label }}
                                </span>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="px-8 py-5 bg-gray-50 border-t border-gray-100">
                            <div class="flex justify-end">
                                <BaseButton variant="secondary" @click="showDetailModal = false">
                                    Fechar
                                </BaseButton>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active {
    transition: all 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}

.modal-enter-from .animate-scale-in,
.modal-leave-to .animate-scale-in {
    transform: scale(0.95) translateY(20px);
}
</style>
