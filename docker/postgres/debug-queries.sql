-- ==========================================================================
-- PostgreSQL Debug Queries - EcommPilot
-- ==========================================================================
-- Queries úteis para diagnosticar problemas durante sincronizações pesadas
-- ==========================================================================

-- 1. CONEXÕES ATIVAS
-- Mostra todas as conexões ativas e seu estado
SELECT
    pid,
    usename,
    application_name,
    client_addr,
    state,
    state_change,
    now() - state_change AS time_in_state,
    query
FROM pg_stat_activity
WHERE datname = current_database()
ORDER BY state_change;

-- 2. QUERIES LENTAS
-- Mostra queries rodando há mais de 30 segundos
SELECT
    pid,
    now() - query_start AS duration,
    state,
    wait_event_type,
    wait_event,
    LEFT(query, 100) AS query
FROM pg_stat_activity
WHERE
    state != 'idle'
    AND now() - query_start > interval '30 seconds'
    AND datname = current_database()
ORDER BY duration DESC;

-- 3. LOCKS BLOQUEANDO
-- Mostra locks que estão bloqueando outras transações
SELECT
    blocked_locks.pid AS blocked_pid,
    blocked_activity.usename AS blocked_user,
    blocking_locks.pid AS blocking_pid,
    blocking_activity.usename AS blocking_user,
    blocked_activity.query AS blocked_statement,
    blocking_activity.query AS blocking_statement,
    blocked_activity.state AS blocked_state,
    blocking_activity.state AS blocking_state
FROM pg_catalog.pg_locks blocked_locks
JOIN pg_catalog.pg_stat_activity blocked_activity ON blocked_activity.pid = blocked_locks.pid
JOIN pg_catalog.pg_locks blocking_locks
    ON blocking_locks.locktype = blocked_locks.locktype
    AND blocking_locks.database IS NOT DISTINCT FROM blocked_locks.database
    AND blocking_locks.relation IS NOT DISTINCT FROM blocked_locks.relation
    AND blocking_locks.page IS NOT DISTINCT FROM blocked_locks.page
    AND blocking_locks.tuple IS NOT DISTINCT FROM blocked_locks.tuple
    AND blocking_locks.virtualxid IS NOT DISTINCT FROM blocked_locks.virtualxid
    AND blocking_locks.transactionid IS NOT DISTINCT FROM blocked_locks.transactionid
    AND blocking_locks.classid IS NOT DISTINCT FROM blocked_locks.classid
    AND blocking_locks.objid IS NOT DISTINCT FROM blocked_locks.objid
    AND blocking_locks.objsubid IS NOT DISTINCT FROM blocked_locks.objsubid
    AND blocking_locks.pid != blocked_locks.pid
JOIN pg_catalog.pg_stat_activity blocking_activity ON blocking_activity.pid = blocking_locks.pid
WHERE NOT blocked_locks.granted;

-- 4. TAMANHO DAS TABELAS
-- Mostra tamanho das tabelas e seus índices
SELECT
    schemaname,
    tablename,
    pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) AS total_size,
    pg_size_pretty(pg_relation_size(schemaname||'.'||tablename)) AS table_size,
    pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename) - pg_relation_size(schemaname||'.'||tablename)) AS indexes_size
FROM pg_tables
WHERE schemaname = 'public'
ORDER BY pg_total_relation_size(schemaname||'.'||tablename) DESC
LIMIT 20;

-- 5. ESTATÍSTICAS DE TABELAS (VACUUM)
-- Mostra quando foi o último VACUUM/ANALYZE e quantas tuplas mortas existem
SELECT
    schemaname,
    relname,
    n_tup_ins AS inserts,
    n_tup_upd AS updates,
    n_tup_del AS deletes,
    n_live_tup AS live_rows,
    n_dead_tup AS dead_rows,
    ROUND(100.0 * n_dead_tup / NULLIF(n_live_tup + n_dead_tup, 0), 2) AS dead_ratio,
    last_vacuum,
    last_autovacuum,
    last_analyze,
    last_autoanalyze
FROM pg_stat_user_tables
WHERE schemaname = 'public'
ORDER BY n_dead_tup DESC;

-- 6. ÍNDICES NÃO UTILIZADOS
-- Mostra índices que nunca foram usados (candidatos para remoção)
SELECT
    schemaname,
    tablename,
    indexname,
    idx_scan,
    idx_tup_read,
    idx_tup_fetch,
    pg_size_pretty(pg_relation_size(indexrelid)) AS index_size
FROM pg_stat_user_indexes
WHERE
    schemaname = 'public'
    AND idx_scan = 0
    AND indexrelname NOT LIKE '%_pkey'
ORDER BY pg_relation_size(indexrelid) DESC;

-- 7. CACHE HIT RATIO
-- Mostra eficiência do cache (deve ser > 0.99)
SELECT
    sum(heap_blks_read) AS heap_read,
    sum(heap_blks_hit) AS heap_hit,
    ROUND(sum(heap_blks_hit)::numeric / NULLIF(sum(heap_blks_hit) + sum(heap_blks_read), 0), 4) AS cache_hit_ratio
FROM pg_statio_user_tables;

-- 8. ATIVIDADE DE AUTOVACUUM
-- Mostra progresso do autovacuum em tempo real
SELECT
    p.pid,
    now() - a.xact_start AS duration,
    coalesce(wait_event_type ||'.'|| wait_event, 'f') AS waiting,
    CASE
        WHEN a.query ~*'^autovacuum.*to prevent wraparound' THEN 'wraparound'
        WHEN a.query ~*'^vacuum' THEN 'user'
        ELSE 'regular'
    END AS mode,
    p.datname AS database,
    p.relid::regclass AS table,
    p.phase,
    pg_size_pretty(p.heap_blks_total * current_setting('block_size')::int) AS table_size,
    pg_size_pretty(p.heap_blks_scanned * current_setting('block_size')::int) AS scanned,
    pg_size_pretty(p.heap_blks_vacuumed * current_setting('block_size')::int) AS vacuumed,
    ROUND(100.0 * p.heap_blks_scanned / p.heap_blks_total, 1) AS scanned_pct,
    ROUND(100.0 * p.heap_blks_vacuumed / p.heap_blks_total, 1) AS vacuumed_pct,
    p.index_vacuum_count,
    ROUND(100.0 * p.num_dead_tuples / p.max_dead_tuples,1) AS dead_pct
FROM pg_stat_progress_vacuum p
JOIN pg_stat_activity a USING (pid)
ORDER BY now() - a.xact_start DESC;

-- 9. CONFIGURAÇÕES ATUAIS
-- Mostra configurações importantes do PostgreSQL
SELECT
    name,
    setting,
    unit,
    short_desc
FROM pg_settings
WHERE name IN (
    'max_connections',
    'shared_buffers',
    'effective_cache_size',
    'work_mem',
    'maintenance_work_mem',
    'wal_buffers',
    'checkpoint_completion_target',
    'statement_timeout',
    'lock_timeout',
    'autovacuum_max_workers'
)
ORDER BY name;

-- 10. TOP QUERIES LENTAS (pg_stat_statements)
-- Requer extensão pg_stat_statements habilitada
SELECT
    substring(query, 1, 100) AS query,
    calls,
    ROUND(total_exec_time::numeric, 2) AS total_time_ms,
    ROUND(mean_exec_time::numeric, 2) AS avg_time_ms,
    ROUND(max_exec_time::numeric, 2) AS max_time_ms
FROM pg_stat_statements
WHERE dbid = (SELECT oid FROM pg_database WHERE datname = current_database())
ORDER BY total_exec_time DESC
LIMIT 20;

-- ==========================================================================
-- QUERIES DE MANUTENÇÃO
-- ==========================================================================

-- LIMPAR CONEXÕES IDLE (cuidado em produção!)
-- Descomente para executar:
-- SELECT pg_terminate_backend(pid)
-- FROM pg_stat_activity
-- WHERE state = 'idle'
--   AND state_change < now() - interval '10 minutes'
--   AND pid <> pg_backend_pid();

-- VACUUM MANUAL (se autovacuum não estiver dando conta)
-- Descomente para executar:
-- VACUUM ANALYZE synced_orders;
-- VACUUM ANALYZE synced_products;

-- REINDEX (se índices estiverem fragmentados)
-- Descomente para executar:
-- REINDEX TABLE synced_orders;

-- RESETAR ESTATÍSTICAS (para começar monitoramento limpo)
-- Descomente para executar:
-- SELECT pg_stat_statements_reset();
-- SELECT pg_stat_reset();
