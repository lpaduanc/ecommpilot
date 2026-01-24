# PostgreSQL Configuration - EcommPilot

## Configurações Otimizadas para Bulk Operations

Este PostgreSQL está otimizado para suportar sincronizações pesadas (~100k pedidos) sem cair.

### Configurações Principais

**Conexões:**
- `max_connections: 300` - Suporta múltiplos workers simultaneamente
- Pool de conexões Laravel: 2-10 conexões por worker

**Memória (para container com 2GB RAM):**
- `shared_buffers: 512MB` (25% RAM)
- `effective_cache_size: 1536MB` (75% RAM)
- `work_mem: 32MB` - Para sorts e hash tables em bulk operations
- `maintenance_work_mem: 256MB` - Para VACUUM e índices

**WAL & Checkpoints:**
- `wal_buffers: 16MB`
- `min_wal_size: 1GB` / `max_wal_size: 4GB`
- `checkpoint_completion_target: 0.9` - Espalha I/O do checkpoint

**Timeouts:**
- `statement_timeout: 600000ms` (10 minutos) - Jobs podem demorar
- `idle_in_transaction_session_timeout: 300000ms` (5 minutos)
- `lock_timeout: 60000ms` (1 minuto)

**Autovacuum (crucial para bulk operations):**
- `autovacuum_max_workers: 4` - Limpa dead tuples rapidamente
- `autovacuum_naptime: 30s` - Roda frequentemente
- Vacuum com 10% de dead tuples, analyze com 5% de mudanças

### Monitoramento

#### 1. Ver conexões ativas

```bash
docker-compose exec postgres psql -U postgres -d laravel -c "
SELECT count(*), state
FROM pg_stat_activity
GROUP BY state;
"
```

#### 2. Ver queries lentas (> 5s)

```bash
docker-compose exec postgres psql -U postgres -d laravel -c "
SELECT pid, now() - query_start AS duration, state, query
FROM pg_stat_activity
WHERE state != 'idle'
  AND now() - query_start > interval '5 seconds'
ORDER BY duration DESC;
"
```

#### 3. Ver locks bloqueando

```bash
docker-compose exec postgres psql -U postgres -d laravel -c "
SELECT
    l.pid,
    l.mode,
    l.granted,
    a.state,
    a.query
FROM pg_locks l
JOIN pg_stat_activity a ON l.pid = a.pid
WHERE NOT l.granted
ORDER BY a.query_start;
"
```

#### 4. Ver estatísticas de tabelas

```bash
docker-compose exec postgres psql -U postgres -d laravel -c "
SELECT
    schemaname,
    relname,
    n_live_tup AS live_rows,
    n_dead_tup AS dead_rows,
    last_vacuum,
    last_autovacuum
FROM pg_stat_user_tables
ORDER BY n_dead_tup DESC
LIMIT 10;
"
```

#### 5. Ver uso de cache

```bash
docker-compose exec postgres psql -U postgres -d laravel -c "
SELECT
    sum(heap_blks_read) AS heap_read,
    sum(heap_blks_hit) AS heap_hit,
    sum(heap_blks_hit) / (sum(heap_blks_hit) + sum(heap_blks_read)) AS cache_hit_ratio
FROM pg_statio_user_tables;
"
```

### Troubleshooting

#### Erro: "could not translate host name postgres to address"

**Causa:** Container app tentando conectar antes do postgres estar pronto.

**Solução:**
1. Verifique que o health check está passando: `docker-compose ps`
2. Reinicie o container app: `docker-compose restart app`
3. Se persistir, reinicie tudo: `docker-compose down && docker-compose up -d`

#### Erro: "No query results for model [App\Models\Store]"

**Causa:** Conexão com banco foi perdida durante job longo.

**Solução:**
- O job já tem `DB::reconnect()` a cada página
- Verifique logs do postgres: `docker-compose logs postgres`
- Verifique se container tem memória suficiente: `docker stats`

#### Container caindo durante sync

**Sintomas:** Container postgres reinicia sozinho.

**Diagnóstico:**
```bash
# Ver uso de memória
docker stats ecommpilot-postgres

# Ver logs de crash
docker-compose logs postgres | grep -i "error\|fatal\|crash"
```

**Soluções:**
1. **OOM (Out of Memory):**
   - Aumente memória do container no `docker-compose.yml`
   - Ou reduza `shared_buffers` e `work_mem` no `postgresql.conf`

2. **Muitas conexões:**
   - Reduza número de workers simultâneos
   - Ou aumente `max_connections` (com mais memória)

3. **Disco cheio:**
   - Limpe volumes antigos: `docker volume prune`
   - Aumente espaço disponível para Docker

#### Performance ruim em bulk inserts

**Sintomas:** Sync muito lento, CPU alta.

**Soluções:**
1. Verifique se autovacuum está rodando:
   ```sql
   SELECT * FROM pg_stat_progress_vacuum;
   ```

2. Se necessário, rode VACUUM manual:
   ```bash
   docker-compose exec postgres psql -U postgres -d laravel -c "VACUUM ANALYZE synced_orders;"
   ```

3. Verifique índices:
   ```bash
   docker-compose exec postgres psql -U postgres -d laravel -c "
   SELECT schemaname, tablename, indexname, idx_scan
   FROM pg_stat_user_indexes
   WHERE schemaname = 'public'
   ORDER BY idx_scan;
   "
   ```

### Backup & Recovery

#### Backup do banco

```bash
docker-compose exec postgres pg_dump -U postgres laravel > backup_$(date +%Y%m%d_%H%M%S).sql
```

#### Restore do backup

```bash
cat backup_20260122_150000.sql | docker-compose exec -T postgres psql -U postgres laravel
```

### Performance Tuning Adicional

Se ainda tiver problemas de performance, considere:

1. **Aumentar memória do container:**
   ```yaml
   deploy:
     resources:
       limits:
         memory: 4G  # Era 2G
   ```

2. **Usar conexões persistentes (com cuidado):**
   ```env
   DB_PERSISTENT=true  # No .env
   ```

3. **Particionar tabelas grandes:**
   - Se `synced_orders` > 1M registros
   - Particione por loja ou data

4. **Índices parciais:**
   ```sql
   CREATE INDEX idx_active_orders ON synced_orders (id)
   WHERE deleted_at IS NULL;
   ```

### Limites Testados

Esta configuração foi dimensionada para:
- ✅ 100k pedidos sincronizados em batch
- ✅ Jobs rodando por 30 minutos contínuos
- ✅ 5-10 workers simultâneos
- ✅ Bulk upserts de 200 registros por vez
- ✅ Tabelas com milhões de linhas

Se ultrapassar esses limites, considere escalar verticalmente (mais RAM/CPU) ou particionar dados.
