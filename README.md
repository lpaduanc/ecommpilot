# Ecommpilot - E-commerce AI Analytics Platform

Plataforma de análises inteligentes com IA para e-commerce, integrando com Nuvemshop e oferecendo sugestões personalizadas para aumentar vendas.

## 📋 Requisitos

- PHP 8.2+
- Composer
- Node.js 18+
- MySQL 8.0+
- Redis (opcional, para filas)

## 🚀 Instalação

### 1. Clone o Repositório

```bash
cd C:\projects
git clone <repository-url> ecommpilot
cd ecommpilot
```

### 2. Instalar Dependências PHP

```bash
composer install
```

### 3. Instalar Dependências JavaScript

```bash
npm install
```

### 4. Configurar Ambiente

```bash
cp .env.example .env
php artisan key:generate
```

### 5. Configurar o arquivo `.env`

```env
# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ecommpilot
DB_USERNAME=root
DB_PASSWORD=

# OpenAI API (para análises IA)
OPENAI_API_KEY=sk-your-api-key-here
OPENAI_ORGANIZATION=your-org-id (opcional)

# Nuvemshop Integration
NUVEMSHOP_CLIENT_ID=your-client-id
NUVEMSHOP_CLIENT_SECRET=your-client-secret
NUVEMSHOP_REDIRECT_URI=http://localhost:8000/api/integrations/nuvemshop/callback

# Queue (opcional - use database ou redis)
QUEUE_CONNECTION=database
```

### 6. Executar Migrações e Seeders

```bash
php artisan migrate
php artisan db:seed
```

### 7. Compilar Assets

```bash
# Desenvolvimento
npm run dev

# Produção
npm run build
```

### 8. Iniciar Servidor

```bash
php artisan serve
```

A aplicação estará disponível em `http://localhost:8000`

## 👤 Credenciais de Acesso (Admin)

- **E-mail:** admin@plataforma.com
- **Senha:** changeme123

⚠️ **Importante:** A senha deve ser alterada no primeiro login.

## 🔧 Configuração de Serviços

### OpenAI API

1. Acesse [platform.openai.com](https://platform.openai.com)
2. Crie uma conta ou faça login
3. Vá em API Keys
4. Crie uma nova chave
5. Adicione ao `.env` como `OPENAI_API_KEY`

### Nuvemshop API

1. Acesse [partners.nuvemshop.com.br](https://partners.nuvemshop.com.br)
2. Crie um aplicativo
3. Configure as permissões: `read_products`, `read_orders`, `read_customers`
4. Configure a URL de callback
5. Copie Client ID e Client Secret para o `.env`

## 📁 Estrutura do Projeto

```
ecommpilot/
├── app/
│   ├── Enums/              # Enumeradores
│   ├── Http/
│   │   ├── Controllers/    # Controllers da API
│   │   ├── Requests/       # Form Requests
│   │   └── Resources/      # API Resources
│   ├── Jobs/               # Jobs para filas
│   ├── Models/             # Modelos Eloquent
│   └── Services/           # Lógica de negócio
├── database/
│   ├── migrations/         # Migrações do banco
│   └── seeders/            # Seeders
├── resources/
│   ├── css/                # Estilos Tailwind
│   ├── js/                 # Vue.js SPA
│   │   ├── components/     # Componentes Vue
│   │   ├── router/         # Rotas Vue Router
│   │   ├── services/       # Serviços de API
│   │   ├── stores/         # Pinia Stores
│   │   └── views/          # Páginas Vue
│   └── views/              # Blade templates
└── routes/
    ├── api.php             # Rotas da API
    └── web.php             # Rotas web (SPA)
```

## 🎯 Funcionalidades

### Dashboard
- Estatísticas em tempo real (receita, pedidos, produtos)
- Gráficos de receita, status de pedidos, top produtos
- Alertas de estoque baixo
- Filtros por período

### Integrações
- Conexão OAuth com Nuvemshop
- Sincronização automática de produtos, pedidos e clientes
- Status de sincronização em tempo real

### Análises IA
- Análises completas da loja usando GPT-4o
- Sugestões priorizadas por impacto
- Alertas e oportunidades identificadas
- Sistema de créditos

### Chat IA
- Assistente de marketing inteligente
- Contexto das análises anteriores
- Histórico de conversas

### Admin Panel
- Gestão de clientes
- Gestão de usuários
- Métricas da plataforma

## 🔄 Executar Filas (Background Jobs)

```bash
php artisan queue:work
```

Para produção, use Supervisor ou similar.

## 🧪 Testes

```bash
php artisan test
```

## 📝 Comandos Úteis

```bash
# Limpar caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Sincronização manual (via tinker)
php artisan tinker
>>> $store = App\Models\Store::first();
>>> App\Jobs\SyncStoreDataJob::dispatch($store);

# Listar rotas
php artisan route:list
```

## 🌐 URLs da Aplicação

- **Frontend:** http://localhost:8000
- **API:** http://localhost:8000/api

## 📜 Licença

Projeto proprietário - Todos os direitos reservados.
