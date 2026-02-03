import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { createRouter, createMemoryHistory } from 'vue-router';
import LoginView from '@/views/auth/LoginView.vue';
import { useAuthStore } from '@/stores/authStore';
import { useNotificationStore } from '@/stores/notificationStore';

// Criar router de teste
const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    { path: '/', name: 'home', component: { template: '<div>Home</div>' } },
    { path: '/dashboard', name: 'dashboard', component: { template: '<div>Dashboard</div>' } },
    { path: '/login', name: 'login', component: LoginView },
    { path: '/register', name: 'register', component: { template: '<div>Register</div>' } },
    { path: '/forgot-password', name: 'forgot-password', component: { template: '<div>Forgot</div>' } },
  ],
});

// Mock dos componentes base
vi.mock('@/components/common/BaseButton.vue', () => ({
  default: {
    name: 'BaseButton',
    template: '<button :type="type" :disabled="disabled || loading" @click="$emit(\'click\')"><slot /></button>',
    props: ['type', 'loading', 'disabled', 'fullWidth', 'size'],
  },
}));

vi.mock('@/components/common/BaseInput.vue', () => ({
  default: {
    name: 'BaseInput',
    template: `
      <div>
        <label v-if="label">{{ label }}</label>
        <input
          :type="type"
          :value="modelValue"
          :placeholder="placeholder"
          :required="required"
          @input="$emit('update:modelValue', $event.target.value)"
        />
        <p v-if="error" class="error">{{ error }}</p>
      </div>
    `,
    props: ['modelValue', 'type', 'label', 'placeholder', 'icon', 'error', 'required'],
    emits: ['update:modelValue'],
  },
}));

// Mock dos ícones
vi.mock('@heroicons/vue/24/outline', () => ({
  EnvelopeIcon: { name: 'EnvelopeIcon', template: '<svg></svg>' },
  LockClosedIcon: { name: 'LockClosedIcon', template: '<svg></svg>' },
  SparklesIcon: { name: 'SparklesIcon', template: '<svg></svg>' },
}));

describe('LoginView', () => {
  let wrapper;
  let authStore;
  let notificationStore;
  let routerPushSpy;

  beforeEach(async () => {
    // Reset mocks
    vi.clearAllMocks();

    // Criar Pinia instance
    const pinia = createPinia();
    setActivePinia(pinia);

    // Reset router para /login
    await router.push('/login');
    await router.isReady();

    // Spy no router.push e limpar chamadas anteriores
    routerPushSpy = vi.spyOn(router, 'push');
    routerPushSpy.mockClear(); // Limpar a chamada do push('/login') acima

    // Obter as stores
    authStore = useAuthStore();
    notificationStore = useNotificationStore();

    // Mock das funções das stores
    vi.spyOn(authStore, 'login').mockResolvedValue({ success: true });
    vi.spyOn(notificationStore, 'success');
    vi.spyOn(notificationStore, 'error');

    // Montar o componente
    wrapper = mount(LoginView, {
      global: {
        plugins: [pinia, router],
      },
    });
  });

  describe('Renderização do Formulário', () => {
    it('deve renderizar o formulário de login corretamente', () => {
      expect(wrapper.find('form').exists()).toBe(true);
      expect(wrapper.text()).toContain('Acesso à Plataforma');
    });

    it('deve renderizar o campo de e-mail com label e placeholder corretos', () => {
      const emailInput = wrapper.findAll('input[type="email"]')[0];
      expect(emailInput.exists()).toBe(true);
      expect(emailInput.attributes('placeholder')).toBe('seu@email.com');
    });

    it('deve renderizar o campo de senha com label e placeholder corretos', () => {
      const passwordInput = wrapper.find('input[type="password"]');
      expect(passwordInput.exists()).toBe(true);
      expect(passwordInput.attributes('placeholder')).toBe('••••••••');
    });

    it('deve renderizar o checkbox "Lembrar-me"', () => {
      const checkbox = wrapper.find('input[type="checkbox"]');
      expect(checkbox.exists()).toBe(true);
    });

    it('deve renderizar o botão de submit', () => {
      const submitButton = wrapper.find('button[type="submit"]');
      expect(submitButton.exists()).toBe(true);
      // O texto pode estar no slot do BaseButton
      expect(wrapper.text()).toContain('Entrar');
    });

    it('deve renderizar links para "Esqueceu sua senha" e "Criar conta"', () => {
      const links = wrapper.findAll('a');
      expect(links.some(link => link.text().includes('Esqueceu sua senha'))).toBe(true);
      expect(links.some(link => link.text().includes('Criar conta'))).toBe(true);
    });
  });

  describe('Validação de Campos Obrigatórios', () => {
    it('deve exibir erro quando o e-mail não é preenchido', async () => {
      const form = wrapper.find('form');
      await form.trigger('submit.prevent');

      await wrapper.vm.$nextTick();

      expect(wrapper.text()).toContain('O e-mail é obrigatório');
    });

    it('deve exibir erro quando a senha não é preenchida', async () => {
      const emailInput = wrapper.findAll('input[type="email"]')[0];
      await emailInput.setValue('teste@example.com');

      const form = wrapper.find('form');
      await form.trigger('submit.prevent');

      await wrapper.vm.$nextTick();

      expect(wrapper.text()).toContain('A senha é obrigatória');
    });

    it('deve exibir erros para ambos os campos quando nenhum é preenchido', async () => {
      const form = wrapper.find('form');
      await form.trigger('submit.prevent');

      await wrapper.vm.$nextTick();

      expect(wrapper.text()).toContain('O e-mail é obrigatório');
      expect(wrapper.text()).toContain('A senha é obrigatória');
    });
  });

  describe('Validação de Formato de E-mail', () => {
    it('deve aceitar e-mails válidos', async () => {
      const emailInput = wrapper.findAll('input[type="email"]')[0];
      const passwordInput = wrapper.find('input[type="password"]');

      await emailInput.setValue('usuario@example.com');
      await passwordInput.setValue('senha123');

      const form = wrapper.find('form');
      await form.trigger('submit.prevent');

      await wrapper.vm.$nextTick();

      expect(wrapper.text()).not.toContain('Digite um e-mail válido');
    });

    it('deve rejeitar e-mail sem @', async () => {
      const emailInput = wrapper.findAll('input[type="email"]')[0];
      await emailInput.setValue('emailinvalido');

      const form = wrapper.find('form');
      await form.trigger('submit.prevent');

      await wrapper.vm.$nextTick();

      expect(wrapper.text()).toContain('Digite um e-mail válido');
    });

    it('deve rejeitar e-mail sem domínio', async () => {
      const emailInput = wrapper.findAll('input[type="email"]')[0];
      await emailInput.setValue('email@');

      const form = wrapper.find('form');
      await form.trigger('submit.prevent');

      await wrapper.vm.$nextTick();

      expect(wrapper.text()).toContain('Digite um e-mail válido');
    });

    it('deve rejeitar e-mail sem nome de usuário', async () => {
      const emailInput = wrapper.findAll('input[type="email"]')[0];
      await emailInput.setValue('@example.com');

      const form = wrapper.find('form');
      await form.trigger('submit.prevent');

      await wrapper.vm.$nextTick();

      expect(wrapper.text()).toContain('Digite um e-mail válido');
    });
  });

  describe('Comportamento do Botão de Submit', () => {
    it('não deve chamar login quando o formulário é inválido', async () => {
      const form = wrapper.find('form');
      await form.trigger('submit.prevent');

      await wrapper.vm.$nextTick();

      expect(authStore.login).not.toHaveBeenCalled();
    });

    it('deve chamar login quando o formulário é válido', async () => {
      const emailInput = wrapper.findAll('input[type="email"]')[0];
      const passwordInput = wrapper.find('input[type="password"]');

      await emailInput.setValue('usuario@example.com');
      await passwordInput.setValue('senha123');

      const form = wrapper.find('form');
      await form.trigger('submit.prevent');

      await wrapper.vm.$nextTick();

      expect(authStore.login).toHaveBeenCalledWith({
        email: 'usuario@example.com',
        password: 'senha123',
        remember: false,
      });
    });

    it('deve enviar remember=true quando o checkbox está marcado', async () => {
      const emailInput = wrapper.findAll('input[type="email"]')[0];
      const passwordInput = wrapper.find('input[type="password"]');
      const checkbox = wrapper.find('input[type="checkbox"]');

      await emailInput.setValue('usuario@example.com');
      await passwordInput.setValue('senha123');
      await checkbox.setValue(true);

      const form = wrapper.find('form');
      await form.trigger('submit.prevent');

      await wrapper.vm.$nextTick();

      expect(authStore.login).toHaveBeenCalledWith({
        email: 'usuario@example.com',
        password: 'senha123',
        remember: true,
      });
    });
  });

  describe('Estados de Loading', () => {
    it('deve mostrar loading durante autenticação', async () => {
      let resolveLogin;
      const loginPromise = new Promise((resolve) => {
        resolveLogin = resolve;
      });

      authStore.login.mockReturnValue(loginPromise);

      const emailInput = wrapper.findAll('input[type="email"]')[0];
      const passwordInput = wrapper.find('input[type="password"]');

      await emailInput.setValue('usuario@example.com');
      await passwordInput.setValue('senha123');

      const form = wrapper.find('form');
      await form.trigger('submit.prevent');

      await wrapper.vm.$nextTick();

      // Verificar que o isLoading está true no componente
      expect(wrapper.vm.isLoading).toBe(true);

      resolveLogin({ success: true });
      await wrapper.vm.$nextTick();
      await new Promise(resolve => setTimeout(resolve, 0));

      expect(wrapper.vm.isLoading).toBe(false);
    });

    it('deve remover loading após autenticação bem-sucedida', async () => {
      authStore.login.mockResolvedValue({ success: true });

      const emailInput = wrapper.findAll('input[type="email"]')[0];
      const passwordInput = wrapper.find('input[type="password"]');

      await emailInput.setValue('usuario@example.com');
      await passwordInput.setValue('senha123');

      // Verificar que isLoading é false antes do submit
      expect(wrapper.vm.isLoading).toBe(false);

      const form = wrapper.find('form');
      await form.trigger('submit.prevent');

      await wrapper.vm.$nextTick();
      await new Promise(resolve => setTimeout(resolve, 0));

      // Após a autenticação, isLoading deve ser false novamente
      expect(wrapper.vm.isLoading).toBe(false);
    });
  });

  describe('Exibição de Mensagens de Erro', () => {
    it('deve exibir mensagem de erro do servidor para o campo email', async () => {
      authStore.login.mockResolvedValue({
        success: false,
        message: 'Credenciais inválidas',
        errors: {
          email: ['Este e-mail não está cadastrado'],
        },
      });

      const emailInput = wrapper.findAll('input[type="email"]')[0];
      const passwordInput = wrapper.find('input[type="password"]');

      await emailInput.setValue('usuario@example.com');
      await passwordInput.setValue('senha123');

      const form = wrapper.find('form');
      await form.trigger('submit.prevent');

      await wrapper.vm.$nextTick();
      await new Promise(resolve => setTimeout(resolve, 0));

      expect(wrapper.text()).toContain('Este e-mail não está cadastrado');
    });

    it('deve exibir mensagem de erro do servidor para o campo senha', async () => {
      authStore.login.mockResolvedValue({
        success: false,
        message: 'Credenciais inválidas',
        errors: {
          password: ['Senha incorreta'],
        },
      });

      const emailInput = wrapper.findAll('input[type="email"]')[0];
      const passwordInput = wrapper.find('input[type="password"]');

      await emailInput.setValue('usuario@example.com');
      await passwordInput.setValue('senha123');

      const form = wrapper.find('form');
      await form.trigger('submit.prevent');

      await wrapper.vm.$nextTick();
      await new Promise(resolve => setTimeout(resolve, 0));

      expect(wrapper.text()).toContain('Senha incorreta');
    });

    it('deve chamar notificationStore.error com a mensagem de erro', async () => {
      authStore.login.mockResolvedValue({
        success: false,
        message: 'Credenciais inválidas',
      });

      const emailInput = wrapper.findAll('input[type="email"]')[0];
      const passwordInput = wrapper.find('input[type="password"]');

      await emailInput.setValue('usuario@example.com');
      await passwordInput.setValue('senha123');

      const form = wrapper.find('form');
      await form.trigger('submit.prevent');

      await wrapper.vm.$nextTick();
      await new Promise(resolve => setTimeout(resolve, 0));

      expect(notificationStore.error).toHaveBeenCalledWith('Credenciais inválidas');
    });

    it('deve limpar erros ao submeter o formulário novamente', async () => {
      // Primeiro submit com erro
      authStore.login.mockResolvedValueOnce({
        success: false,
        errors: {
          email: ['E-mail inválido'],
        },
      });

      const emailInput = wrapper.findAll('input[type="email"]')[0];
      const passwordInput = wrapper.find('input[type="password"]');

      await emailInput.setValue('usuario@example.com');
      await passwordInput.setValue('senha123');

      const form = wrapper.find('form');
      await form.trigger('submit.prevent');

      await wrapper.vm.$nextTick();
      await new Promise(resolve => setTimeout(resolve, 0));

      expect(wrapper.text()).toContain('E-mail inválido');

      // Segundo submit com sucesso
      authStore.login.mockResolvedValueOnce({ success: true });

      await form.trigger('submit.prevent');

      await wrapper.vm.$nextTick();

      expect(wrapper.text()).not.toContain('E-mail inválido');
    });
  });

  describe('Redirecionamento Após Login', () => {
    it('deve redirecionar para "/" após login bem-sucedido', async () => {
      authStore.login.mockResolvedValue({ success: true });

      const emailInput = wrapper.findAll('input[type="email"]')[0];
      const passwordInput = wrapper.find('input[type="password"]');

      await emailInput.setValue('usuario@example.com');
      await passwordInput.setValue('senha123');

      const form = wrapper.find('form');
      await form.trigger('submit.prevent');

      await wrapper.vm.$nextTick();
      await new Promise(resolve => setTimeout(resolve, 0));

      expect(routerPushSpy).toHaveBeenCalledWith('/');
    });

    it('deve redirecionar para a URL em redirect query param após login bem-sucedido', async () => {
      // Remonta o componente com query param
      wrapper.unmount();

      await router.push({ path: '/login', query: { redirect: '/dashboard' } });
      await router.isReady();

      const pinia = createPinia();
      setActivePinia(pinia);

      routerPushSpy = vi.spyOn(router, 'push');

      wrapper = mount(LoginView, {
        global: {
          plugins: [pinia, router],
        },
      });

      const authStoreNew = useAuthStore();
      const notificationStoreNew = useNotificationStore();
      vi.spyOn(authStoreNew, 'login').mockResolvedValue({ success: true });
      vi.spyOn(notificationStoreNew, 'success');

      const emailInput = wrapper.findAll('input[type="email"]')[0];
      const passwordInput = wrapper.find('input[type="password"]');

      await emailInput.setValue('usuario@example.com');
      await passwordInput.setValue('senha123');

      const form = wrapper.find('form');
      await form.trigger('submit.prevent');

      await wrapper.vm.$nextTick();
      await new Promise(resolve => setTimeout(resolve, 0));

      expect(routerPushSpy).toHaveBeenCalledWith('/dashboard');
    });

    it('deve chamar notificationStore.success após login bem-sucedido', async () => {
      authStore.login.mockResolvedValue({ success: true });

      const emailInput = wrapper.findAll('input[type="email"]')[0];
      const passwordInput = wrapper.find('input[type="password"]');

      await emailInput.setValue('usuario@example.com');
      await passwordInput.setValue('senha123');

      const form = wrapper.find('form');
      await form.trigger('submit.prevent');

      await wrapper.vm.$nextTick();
      await new Promise(resolve => setTimeout(resolve, 0));

      expect(notificationStore.success).toHaveBeenCalledWith('Login realizado com sucesso!');
    });
  });

  describe('Interação com AuthStore', () => {
    it('deve passar as credenciais corretas para authStore.login', async () => {
      const emailInput = wrapper.findAll('input[type="email"]')[0];
      const passwordInput = wrapper.find('input[type="password"]');

      await emailInput.setValue('teste@example.com');
      await passwordInput.setValue('minhaSenha123');

      const form = wrapper.find('form');
      await form.trigger('submit.prevent');

      await wrapper.vm.$nextTick();

      expect(authStore.login).toHaveBeenCalledWith({
        email: 'teste@example.com',
        password: 'minhaSenha123',
        remember: false,
      });
    });

    it('deve lidar com resposta de sucesso do authStore', async () => {
      authStore.login.mockResolvedValue({ success: true });

      const emailInput = wrapper.findAll('input[type="email"]')[0];
      const passwordInput = wrapper.find('input[type="password"]');

      await emailInput.setValue('usuario@example.com');
      await passwordInput.setValue('senha123');

      const form = wrapper.find('form');
      await form.trigger('submit.prevent');

      await wrapper.vm.$nextTick();
      await new Promise(resolve => setTimeout(resolve, 0));

      expect(notificationStore.success).toHaveBeenCalled();
      expect(routerPushSpy).toHaveBeenCalled();
    });

    it('deve lidar com resposta de erro do authStore', async () => {
      authStore.login.mockResolvedValue({
        success: false,
        message: 'Erro ao fazer login',
      });

      const emailInput = wrapper.findAll('input[type="email"]')[0];
      const passwordInput = wrapper.find('input[type="password"]');

      await emailInput.setValue('usuario@example.com');
      await passwordInput.setValue('senha123');

      const form = wrapper.find('form');
      await form.trigger('submit.prevent');

      await wrapper.vm.$nextTick();
      await new Promise(resolve => setTimeout(resolve, 0));

      expect(notificationStore.error).toHaveBeenCalledWith('Erro ao fazer login');
      expect(routerPushSpy).not.toHaveBeenCalled();
    });
  });

  describe('Integração Completa', () => {
    it('deve completar o fluxo de login com sucesso', async () => {
      authStore.login.mockResolvedValue({ success: true });

      const emailInput = wrapper.findAll('input[type="email"]')[0];
      const passwordInput = wrapper.find('input[type="password"]');
      const checkbox = wrapper.find('input[type="checkbox"]');

      // Preencher formulário
      await emailInput.setValue('usuario@example.com');
      await passwordInput.setValue('senha123');
      await checkbox.setValue(true);

      // Verificar que não há erros antes do submit
      expect(wrapper.text()).not.toContain('obrigatório');

      // Submit
      const form = wrapper.find('form');
      await form.trigger('submit.prevent');

      await wrapper.vm.$nextTick();
      await new Promise(resolve => setTimeout(resolve, 0));

      // Verificações
      expect(authStore.login).toHaveBeenCalledWith({
        email: 'usuario@example.com',
        password: 'senha123',
        remember: true,
      });
      expect(notificationStore.success).toHaveBeenCalledWith('Login realizado com sucesso!');
      expect(routerPushSpy).toHaveBeenCalledWith('/');
    });

    it('deve completar o fluxo de login com erro de validação do servidor', async () => {
      authStore.login.mockResolvedValue({
        success: false,
        message: 'Credenciais inválidas',
        errors: {
          email: ['E-mail ou senha incorretos'],
        },
      });

      const emailInput = wrapper.findAll('input[type="email"]')[0];
      const passwordInput = wrapper.find('input[type="password"]');

      await emailInput.setValue('usuario@example.com');
      await passwordInput.setValue('senhaerrada');

      const form = wrapper.find('form');
      await form.trigger('submit.prevent');

      await wrapper.vm.$nextTick();
      await new Promise(resolve => setTimeout(resolve, 0));

      // Verificações
      expect(authStore.login).toHaveBeenCalled();
      expect(wrapper.text()).toContain('E-mail ou senha incorretos');
      expect(notificationStore.error).toHaveBeenCalledWith('Credenciais inválidas');
      expect(routerPushSpy).not.toHaveBeenCalled();
    });
  });
});
