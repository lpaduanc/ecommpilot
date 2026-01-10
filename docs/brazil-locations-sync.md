# Sistema de Sincronização de Estados e Cidades do Brasil

Sistema que sincroniza estados e cidades brasileiras usando a API do IBGE, armazenando os dados em arquivos JSON locais para acesso rápido.

## Arquitetura

### Estrutura de Arquivos

```
storage/app/data/brazil/
├── states.json         # Lista de 27 estados brasileiros
├── cities.json         # Cidades agrupadas por UF (~5570 cidades)
└── last_sync.txt       # Timestamp da última sincronização
```

### Componentes

#### 1. Job de Sincronização
**Arquivo**: `app/Jobs/SyncBrazilLocationsJob.php`

- Busca estados da API do IBGE
- Para cada estado, busca suas cidades
- Salva nos arquivos JSON
- Registra timestamp da sincronização
- Retry: 3 tentativas com 60s de backoff
- Timeout: 5 minutos
- Delay de 0.1s entre requisições para evitar rate limiting

**API do IBGE**:
- Estados: `https://servicodados.ibge.gov.br/api/v1/localidades/estados?orderBy=nome`
- Cidades: `https://servicodados.ibge.gov.br/api/v1/localidades/estados/{uf}/municipios?orderBy=nome`

#### 2. Service
**Arquivo**: `app/Services/BrazilLocationsService.php`

Métodos:
- `getStates()` - Retorna lista de estados do JSON
- `getCitiesByState($uf)` - Retorna cidades de um estado específico
- `sync()` - Dispara job de sincronização
- `getLastSyncDate()` - Retorna data da última sincronização
- `needsSync()` - Verifica se precisa sincronizar (mais de 7 dias)
- `getSyncStatus()` - Retorna informações de status

#### 3. Controller
**Arquivo**: `app/Http/Controllers/Api/LocationController.php`

Endpoints:
- `GET /api/locations/states` - Lista estados (público)
- `GET /api/locations/cities/{uf}` - Lista cidades por estado (público)
- `POST /api/admin/locations/sync` - Dispara sincronização (admin)
- `GET /api/admin/locations/sync-status` - Status da sincronização (admin)

#### 4. Comando Artisan
**Arquivo**: `app/Console/Commands/SyncBrazilLocationsCommand.php`

```bash
php artisan locations:sync-brazil
```

Executa a sincronização de forma síncrona.

#### 5. Scheduler
**Arquivo**: `routes/console.php`

```php
Schedule::job(new SyncBrazilLocationsJob)->weeklyOn(0, '3:00');
```

Sincroniza automaticamente todo domingo às 3h da manhã.

## Formato dos Dados

### states.json
```json
[
  {
    "id": 35,
    "sigla": "SP",
    "nome": "São Paulo"
  },
  {
    "id": 33,
    "sigla": "RJ",
    "nome": "Rio de Janeiro"
  }
]
```

### cities.json
```json
{
  "SP": [
    {
      "id": 3550308,
      "nome": "São Paulo"
    },
    {
      "id": 3509502,
      "nome": "Campinas"
    }
  ],
  "RJ": [
    {
      "id": 3304557,
      "nome": "Rio de Janeiro"
    }
  ]
}
```

## Uso

### API Endpoints

#### Listar Estados
```bash
GET /api/locations/states
```

Resposta:
```json
{
  "data": [
    {
      "id": 35,
      "sigla": "SP",
      "nome": "São Paulo"
    }
  ]
}
```

#### Listar Cidades por Estado
```bash
GET /api/locations/cities/SP
```

Resposta:
```json
{
  "data": [
    {
      "id": 3550308,
      "nome": "São Paulo"
    }
  ]
}
```

#### Forçar Sincronização (Admin)
```bash
POST /api/admin/locations/sync
Authorization: Bearer {token}
```

Resposta:
```json
{
  "message": "Sincronização de localidades iniciada com sucesso."
}
```

#### Status da Sincronização (Admin)
```bash
GET /api/admin/locations/sync-status
Authorization: Bearer {token}
```

Resposta:
```json
{
  "last_sync": "2026-01-10T02:27:46.767021Z",
  "needs_sync": false,
  "states_count": 27,
  "cities_count": 5570
}
```

### Via Service

```php
use App\Services\BrazilLocationsService;

$service = new BrazilLocationsService();

// Obter estados
$states = $service->getStates();

// Obter cidades de um estado
$cities = $service->getCitiesByState('SP');

// Verificar se precisa sincronizar
if ($service->needsSync()) {
    $service->sync();
}

// Obter status
$status = $service->getSyncStatus();
```

### Via Command

```bash
# Sincronização manual
php artisan locations:sync-brazil

# Verificar agendamento
php artisan schedule:list
```

## Integração com OrderController

O `OrderController` usa o serviço para retornar a lista completa de estados brasileiros no método `filters()`:

```php
// Get Brazil states from the new service
$states = $this->locationsService->getStates();
$statesList = array_map(function ($state) {
    return [
        'sigla' => $state['sigla'],
        'nome' => $state['nome'],
    ];
}, $states);
```

Isso permite que o frontend mostre todos os estados nos filtros, não apenas os estados que aparecem em pedidos.

## Tratamento de Erros

O sistema possui tratamento robusto de erros:

1. **Falha na API do IBGE**: Log de erro e retry automático
2. **Arquivo JSON corrompido**: Retorna array vazio e loga erro
3. **Arquivo não encontrado**: Retorna array vazio (graceful degradation)
4. **Falha em estado específico**: Continua sincronizando outros estados

## Logs

Todos os eventos importantes são logados:

```
[2026-01-10 02:27:33] Starting Brazil locations sync from IBGE API
[2026-01-10 02:27:34] Synced states {"count": 27}
[2026-01-10 02:27:35] Synced cities for state AC {"count": 22}
[2026-01-10 02:27:46] Brazil locations sync completed successfully
```

## Testes

Execute os testes com:

```bash
php artisan test --filter=BrazilLocationsTest
```

Testes incluídos:
- API endpoints (público e admin)
- Service methods
- Job execution
- Estrutura dos arquivos JSON

## Performance

- **Primeira sincronização**: ~15 segundos (27 estados + ~5570 cidades)
- **Consulta de estados**: < 1ms (leitura de JSON)
- **Consulta de cidades**: < 5ms (leitura de JSON)
- **Tamanho dos arquivos**:
  - states.json: ~1.5 KB
  - cities.json: ~200 KB

## Requisitos

- PHP 8.2+
- Laravel 12
- Extensão JSON habilitada
- Acesso à API do IBGE (internet)

## Troubleshooting

### Sincronização não executou
```bash
# Verificar se o scheduler está rodando
php artisan schedule:list

# Executar manualmente
php artisan locations:sync-brazil

# Verificar logs
tail -f storage/logs/laravel.log | grep -i brazil
```

### Arquivos JSON vazios
```bash
# Deletar arquivos e sincronizar novamente
rm storage/app/data/brazil/*.json
php artisan locations:sync-brazil
```

### Permissões de arquivo
```bash
# Linux/Mac
chmod -R 775 storage/app/data/brazil

# Windows - executar como administrador se necessário
```

## Manutenção

O sistema é auto-suficiente:
- Sincroniza automaticamente toda semana
- Não requer banco de dados
- Arquivos JSON são pequenos e eficientes
- Logs automáticos para monitoramento
