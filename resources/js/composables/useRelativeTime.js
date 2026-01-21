import { computed } from 'vue';

export function useRelativeTime(date) {
    return computed(() => {
        if (!date) return '';

        const now = new Date();
        const created = new Date(date);
        const diffMs = now - created;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);

        if (diffMins < 1) return 'Agora mesmo';
        if (diffMins < 60) return `Há ${diffMins} ${diffMins === 1 ? 'minuto' : 'minutos'}`;
        if (diffHours < 24) return `Há ${diffHours} ${diffHours === 1 ? 'hora' : 'horas'}`;
        if (diffDays < 7) return `Há ${diffDays} ${diffDays === 1 ? 'dia' : 'dias'}`;

        return created.toLocaleDateString('pt-BR');
    });
}

export function formatRelativeTime(date) {
    if (!date) return '';

    const now = new Date();
    const created = new Date(date);
    const diffMs = now - created;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 1) return 'Agora mesmo';
    if (diffMins < 60) return `Há ${diffMins} ${diffMins === 1 ? 'minuto' : 'minutos'}`;
    if (diffHours < 24) return `Há ${diffHours} ${diffHours === 1 ? 'hora' : 'horas'}`;
    if (diffDays < 7) return `Há ${diffDays} ${diffDays === 1 ? 'dia' : 'dias'}`;

    return created.toLocaleDateString('pt-BR');
}
