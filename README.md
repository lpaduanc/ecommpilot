# Ecommpilot - E-commerce AI Analytics Platform

Plataforma de análises inteligentes com IA para e-commerce, integrando com Nuvemshop e oferecendo sugestões personalizadas para aumentar vendas.

## 📋 Requisitos

- PHP 8.2+
- Composer
- Node.js 18+
- PostgreSQL 16+ (ou MySQL 8.0+)
- Redis (para cache e filas)

---

## 🐳 Instalação com Docker (Recomendado)

A forma mais fácil de rodar o projeto é usando Docker, que já vem com todos os serviços configurados.

### Pré-requisitos

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) instalado
- Git

### Serviços incluídos

| Serviço | Descrição | Porta |
|---------|-----------|-------|
| **app** | PHP 8.2-FPM com Laravel | 9000 (interno) |
| **nginx** | Servidor web | 8000 |
| **postgres** | PostgreSQL 16 + pgvector (embeddings) | 5433 |
| **redis** | Cache e filas | 6379 |
| **node** | Vite dev server com HMR | 5173 |
| **horizon** | Laravel Horizon (gerenciador de filas) | - |

### 1. Clone o Repositório

```bash
git clone <repository-url> ecommpilot
cd ecommpilot
```

### 2. Configurar Ambiente

```bash
# Copiar arquivo de ambiente para Docker
cp .env.docker .env
```

Edite o arquivo `.env` e adicione suas API keys:

```env
# AI Provider (escolha um)
AI_PROVIDER=anthropic
ANTHROPIC_API_KEY=sua-chave-aqui
# ou
OPENAI_API_KEY=sua-chave-aqui
# ou
GOOGLE_AI_API_KEY=sua-chave-aqui

# Nuvemshop (se for usar integração)
NUVEMSHOP_CLIENT_ID=seu-client-id
NUVEMSHOP_CLIENT_SECRET=seu-client-secret
```

### 3. Build e Iniciar

```bash
# Build das imagens (primeira vez)
docker-compose build

# Iniciar backend (PHP, Nginx, PostgreSQL, Redis, Horizon)
docker-compose up -d
```

### 4. Iniciar Frontend (Modo Híbrido - Recomendado para Windows)

Para **melhor performance no Windows**, rode o Vite diretamente no Windows ao invés do container:

```bash
# Instalar dependências Node.js (no Windows)
npm install

# Rodar Vite dev server (no Windows)
npm run dev
```

> **Por que modo híbrido?**
> Docker no Windows usa WSL2, que é lento para file watching. Rodar o Vite nativo no Windows elimina esse gargalo, mantendo HMR rápido.

**Alternativa: Rodar Vite no Docker** (mais lento no Windows)
```bash
docker-compose --profile frontend up -d
```

### 5. Configuração Inicial

```bash
# Gerar chave da aplicação (se não existir)
docker-compose exec app php artisan key:generate

# Rodar migrations e seeders
docker-compose exec app php artisan migrate --seed
```

### 6. Acessar a Aplicação

- **Aplicação:** http://localhost:8000
- **Vite HMR:** http://localhost:5173 (se rodando no Windows)
- **Horizon (filas):** http://localhost:8000/horizon

### Comandos Docker Úteis

```bash
# Iniciar serviços
docker-compose up -d

# Parar serviços
docker-compose down

# Ver logs (todos os serviços)
docker-compose logs -f

# Ver logs de um serviço específico
docker-compose logs -f app
docker-compose logs -f horizon

# Executar comandos artisan
docker-compose exec app php artisan <comando>

# Executar comandos composer
docker-compose exec app composer <comando>

# Executar comandos npm
docker-compose exec node npm <comando>

# Acessar shell do container PHP
docker-compose exec app sh

# Reiniciar um serviço
docker-compose restart horizon

# Rebuild após mudanças no Dockerfile
docker-compose build --no-cache
docker-compose up -d

# Limpar tudo (cuidado: apaga dados do banco)
docker-compose down -v
```

### Estrutura Docker

```
docker/
├── php/
│   ├── Dockerfile        # Imagem PHP 8.2-FPM com extensões
│   ├── php.ini           # Configurações PHP
│   └── www.conf          # Configurações PHP-FPM
├── nginx/
│   └── default.conf      # Configuração Nginx com proxy Vite
├── postgres/
│   ├── init/
│   │   └── 01-create-testing-db.sql  # Cria DB de testes + extensões
│   ├── postgresql.conf   # Configurações otimizadas para bulk operations
│   ├── healthcheck.sh    # Health check robusto
│   ├── debug-queries.sql # Queries úteis para debugging
│   └── README.md         # Documentação completa do PostgreSQL
└── scripts/
    ├── entrypoint.sh            # Inicialização do app
    └── horizon-entrypoint.sh    # Inicialização do Horizon

docker-compose.yml    # Orquestração dos serviços
.dockerignore         # Arquivos ignorados no build
.env.docker           # Template de variáveis para Docker
```

### Variáveis de Ambiente Docker

O arquivo `.env.docker` já vem configurado para Docker. As principais diferenças do ambiente local:

| Variável | Valor Docker | Valor Local |
|----------|--------------|-------------|
| `DB_HOST` | `postgres` | `127.0.0.1` |
| `DB_PORT` | `5432` | `5433` |
| `REDIS_HOST` | `redis` | `127.0.0.1` |
| `QUEUE_CONNECTION` | `redis` | `database` |
| `CACHE_STORE` | `redis` | `file` |

### Performance no Windows (WSL2)

O Docker no Windows usa WSL2, que pode ser lento para operações de I/O com volumes montados. A configuração já inclui várias otimizações:

#### Otimizações Aplicadas

| Otimização | Descrição |
|------------|-----------|
| **Volumes nomeados** | `vendor` e `storage/framework` usam volumes Docker (dentro do WSL2) ao invés de bind mounts |
| **OPcache habilitado** | PHP OPcache com revalidação automática - melhora performance sem quebrar hot reload |
| **Gzip no Nginx** | Compressão de respostas para menor transferência |
| **Cache de estáticos** | Arquivos estáticos servidos com cache headers |
| **File watching otimizado** | Polling com intervalo de 2s e diretórios pesados ignorados |

#### Melhores Práticas

1. **Use modo híbrido**: Rode Vite no Windows (`npm run dev`) e backend no Docker
2. **Não edite vendor/node_modules**: Eles estão em volumes Docker, edições locais não refletem
3. **Use `docker-compose exec`**: Para rodar comandos dentro do container

#### Performance Máxima (Opcional)

Para **máxima performance**, mova o projeto para dentro do WSL2:

```bash
# No terminal WSL2 (Ubuntu)
mkdir -p ~/projects
cp -r /mnt/c/projects/ecommpilot ~/projects/
cd ~/projects/ecommpilot
docker-compose up -d
```

Depois, abra o VS Code com a extensão "Remote - WSL" apontando para `~/projects/ecommpilot`.

### PostgreSQL Otimizado

O PostgreSQL está configurado para suportar sincronizações pesadas (~100k pedidos) sem cair.

**Configurações principais:**
- `max_connections: 300` - Suporta múltiplos workers simultâneos
- `shared_buffers: 512MB` / `work_mem: 32MB` - Otimizado para bulk operations
- `statement_timeout: 10 minutos` - Jobs podem demorar
- `autovacuum` agressivo - Limpa dead tuples rapidamente

**Monitoramento:**
```bash
# Ver conexões ativas
docker-compose exec postgres psql -U postgres -d laravel -c "
SELECT count(*), state FROM pg_stat_activity GROUP BY state;
"

# Ver queries lentas (> 5s)
docker-compose exec postgres psql -U postgres -d laravel -f /docker/postgres/debug-queries.sql
```

**Documentação completa:** Ver `docker/postgres/README.md` para troubleshooting e tuning avançado.

### Troubleshooting Docker

**PostgreSQL caindo durante sync:**
```bash
# 1. Ver logs
docker-compose logs postgres | grep -i error

# 2. Ver uso de memória
docker stats ecommpilot-postgres

# 3. Se OOM, aumente memória no docker-compose.yml
# 4. Ou reduza configurações no docker/postgres/postgresql.conf
```

**Erro "could not translate host name postgres":**
```bash
# Container app tentando conectar antes do postgres estar pronto
docker-compose restart app

# Se persistir, verifique health check
docker-compose ps
```

**Erro "No query results for model [Store]":**
```bash
# Conexão foi perdida durante job longo
# O job já tem DB::reconnect() automático
# Verifique logs para ver se postgres reiniciou
docker-compose logs postgres
```

**Erro de permissão em arquivos:**
```bash
# No Windows/Mac isso geralmente não ocorre
# No Linux, ajuste o USER_ID no docker-compose.yml
USER_ID=$(id -u) GROUP_ID=$(id -g) docker-compose up -d
```

**Vite HMR não funciona (modo container):**
```bash
# Se estiver usando Vite no Docker
docker-compose --profile frontend up -d
docker-compose logs node
```

**Horizon não processa jobs:**
```bash
# Verifique os logs
docker-compose logs horizon

# Reinicie o Horizon
docker-compose restart horizon
```

**Container app lento na primeira vez:**
```bash
# Primeira execução instala vendor (pode demorar)
# Acompanhe o progresso:
docker-compose logs -f app
```

**Resetar banco de dados:**
```bash
docker-compose exec app php artisan migrate:fresh --seed
```

**Resetar volumes (recomeçar do zero):**
```bash
docker-compose down -v
docker-compose up -d
```

---

## 🚀 Instalação Manual (Sem Docker)

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

### Com Docker
| Serviço | URL |
|---------|-----|
| Frontend | http://localhost:8000 |
| API | http://localhost:8000/api |
| Horizon | http://localhost:8000/horizon |
| Vite HMR | http://localhost:5173 |
| PostgreSQL | localhost:5433 |
| Redis | localhost:6379 |

### Sem Docker
| Serviço | URL |
|---------|-----|
| Frontend | http://localhost:8000 |
| API | http://localhost:8000/api |

## 📜 Licença

Projeto proprietário - Todos os direitos reservados.
