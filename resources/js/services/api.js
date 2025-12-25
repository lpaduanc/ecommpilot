import axios from 'axios';
import { useNotificationStore } from '../stores/notificationStore';
import { useAuthStore } from '../stores/authStore';

const api = axios.create({
    baseURL: '/api',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

// Response interceptor for error handling
api.interceptors.response.use(
    (response) => response,
    (error) => {
        const notificationStore = useNotificationStore();
        
        if (error.response) {
            switch (error.response.status) {
                case 401:
                    // Unauthorized - logout user
                    const authStore = useAuthStore();
                    authStore.logout();
                    window.location.href = '/login';
                    break;
                    
                case 403:
                    notificationStore.error('Você não tem permissão para realizar esta ação.');
                    break;
                    
                case 404:
                    notificationStore.error('Recurso não encontrado.');
                    break;
                    
                case 422:
                    // Validation error - handled by the calling code
                    break;
                    
                case 429:
                    notificationStore.warning('Muitas tentativas. Por favor, aguarde um momento.');
                    break;
                    
                case 500:
                    notificationStore.error('Erro interno do servidor. Tente novamente mais tarde.');
                    break;
                    
                default:
                    notificationStore.error('Ocorreu um erro. Tente novamente.');
            }
        } else if (error.request) {
            notificationStore.error('Erro de conexão. Verifique sua internet.');
        }
        
        return Promise.reject(error);
    }
);

export default api;

