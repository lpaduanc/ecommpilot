#!/bin/bash
# ==========================================================================
# PostgreSQL Monitoring Script - EcommPilot
# ==========================================================================
# Script auxiliar para monitorar PostgreSQL durante sincronizações pesadas
# ==========================================================================

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configurações
DB_CONTAINER="${DB_CONTAINER:-ecommpilot-postgres}"
DB_USER="${DB_USER:-postgres}"
DB_NAME="${DB_NAME:-laravel}"

# Funções auxiliares
print_header() {
    echo -e "\n${BLUE}========================================${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}========================================${NC}\n"
}

print_success() {
    echo -e "${GREEN}✓${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}⚠${NC} $1"
}

print_error() {
    echo -e "${RED}✗${NC} $1"
}

run_query() {
    docker exec -i "$DB_CONTAINER" psql -U "$DB_USER" -d "$DB_NAME" -t -A -c "$1"
}

# Verifica se container está rodando
if ! docker ps | grep -q "$DB_CONTAINER"; then
    print_error "Container $DB_CONTAINER não está rodando!"
    exit 1
fi

# Menu
show_menu() {
    print_header "PostgreSQL Monitor - EcommPilot"
    echo "1) Ver status geral"
    echo "2) Ver conexões ativas"
    echo "3) Ver queries lentas"
    echo "4) Ver locks bloqueando"
    echo "5) Ver tamanho das tabelas"
    echo "6) Ver estatísticas de VACUUM"
    echo "7) Ver cache hit ratio"
    echo "8) Ver autovacuum em progresso"
    echo "9) Ver configurações importantes"
    echo "10) Ver top queries lentas"
    echo "11) Monitoramento contínuo (atualiza a cada 5s)"
    echo "0) Sair"
    echo ""
    read -p "Escolha uma opção: " choice
}

# Funções de monitoramento
status_geral() {
    print_header "Status Geral"

    echo "Container: $DB_CONTAINER"
    echo "Database: $DB_NAME"
    echo ""

    # Uptime
    uptime=$(run_query "SELECT now() - pg_postmaster_start_time() AS uptime;")
    echo "Uptime: $uptime"

    # Versão
    version=$(run_query "SELECT version();")
    echo "Versão: ${version:0:50}..."

    # Conexões
    total_conn=$(run_query "SELECT count(*) FROM pg_stat_activity;")
    active_conn=$(run_query "SELECT count(*) FROM pg_stat_activity WHERE state = 'active';")
    idle_conn=$(run_query "SELECT count(*) FROM pg_stat_activity WHERE state = 'idle';")
    max_conn=$(run_query "SELECT setting FROM pg_settings WHERE name = 'max_connections';")

    echo ""
    echo "Conexões:"
    echo "  Total: $total_conn / $max_conn"
    echo "  Ativas: $active_conn"
    echo "  Idle: $idle_conn"

    if [ "$total_conn" -gt 250 ]; then
        print_warning "Número de conexões está alto! ($total_conn / $max_conn)"
    else
        print_success "Número de conexões está OK"
    fi

    # Tamanho do banco
    db_size=$(run_query "SELECT pg_size_pretty(pg_database_size('$DB_NAME'));")
    echo ""
    echo "Tamanho do banco: $db_size"
}

conexoes_ativas() {
    print_header "Conexões Ativas"
    docker exec "$DB_CONTAINER" psql -U "$DB_USER" -d "$DB_NAME" -c "
    SELECT
        pid,
        usename,
        application_name,
        state,
        EXTRACT(EPOCH FROM (now() - state_change))::int AS seconds_in_state,
        LEFT(query, 50) AS query
    FROM pg_stat_activity
    WHERE datname = '$DB_NAME'
    ORDER BY state_change;
    "
}

queries_lentas() {
    print_header "Queries Lentas (> 5s)"
    docker exec "$DB_CONTAINER" psql -U "$DB_USER" -d "$DB_NAME" -c "
    SELECT
        pid,
        EXTRACT(EPOCH FROM (now() - query_start))::int AS duration_seconds,
        state,
        LEFT(query, 80) AS query
    FROM pg_stat_activity
    WHERE
        state != 'idle'
        AND now() - query_start > interval '5 seconds'
        AND datname = '$DB_NAME'
    ORDER BY duration_seconds DESC;
    "
}

locks_bloqueando() {
    print_header "Locks Bloqueando"

    count=$(run_query "SELECT count(*) FROM pg_locks WHERE NOT granted;")

    if [ "$count" -eq 0 ]; then
        print_success "Nenhum lock bloqueando encontrado"
    else
        print_warning "Encontrados $count locks bloqueando!"
        docker exec "$DB_CONTAINER" psql -U "$DB_USER" -d "$DB_NAME" -c "
        SELECT
            l.pid,
            l.mode,
            l.granted,
            a.state,
            LEFT(a.query, 60) AS query
        FROM pg_locks l
        JOIN pg_stat_activity a ON l.pid = a.pid
        WHERE NOT l.granted
        ORDER BY a.query_start;
        "
    fi
}

tamanho_tabelas() {
    print_header "Tamanho das Tabelas (Top 10)"
    docker exec "$DB_CONTAINER" psql -U "$DB_USER" -d "$DB_NAME" -c "
    SELECT
        tablename,
        pg_size_pretty(pg_total_relation_size('public.'||tablename)) AS total_size,
        pg_size_pretty(pg_relation_size('public.'||tablename)) AS table_size,
        pg_size_pretty(pg_total_relation_size('public.'||tablename) - pg_relation_size('public.'||tablename)) AS indexes_size
    FROM pg_tables
    WHERE schemaname = 'public'
    ORDER BY pg_total_relation_size('public.'||tablename) DESC
    LIMIT 10;
    "
}

estatisticas_vacuum() {
    print_header "Estatísticas de VACUUM"
    docker exec "$DB_CONTAINER" psql -U "$DB_USER" -d "$DB_NAME" -c "
    SELECT
        relname,
        n_live_tup AS live_rows,
        n_dead_tup AS dead_rows,
        ROUND(100.0 * n_dead_tup / NULLIF(n_live_tup + n_dead_tup, 0), 2) AS dead_ratio,
        last_vacuum,
        last_autovacuum
    FROM pg_stat_user_tables
    WHERE schemaname = 'public'
    ORDER BY n_dead_tup DESC
    LIMIT 10;
    "
}

cache_hit_ratio() {
    print_header "Cache Hit Ratio"

    ratio=$(run_query "
    SELECT ROUND(sum(heap_blks_hit)::numeric / NULLIF(sum(heap_blks_hit) + sum(heap_blks_read), 0), 4)
    FROM pg_statio_user_tables;
    ")

    echo "Cache Hit Ratio: $ratio"

    if (( $(echo "$ratio > 0.99" | bc -l) )); then
        print_success "Cache hit ratio excelente! (> 0.99)"
    elif (( $(echo "$ratio > 0.95" | bc -l) )); then
        print_warning "Cache hit ratio OK, mas pode melhorar (> 0.95)"
    else
        print_error "Cache hit ratio baixo! Considere aumentar shared_buffers"
    fi
}

autovacuum_progresso() {
    print_header "Autovacuum em Progresso"

    count=$(run_query "SELECT count(*) FROM pg_stat_progress_vacuum;")

    if [ "$count" -eq 0 ]; then
        print_success "Nenhum autovacuum rodando no momento"
    else
        print_warning "Encontrados $count autovacuum(s) rodando"
        docker exec "$DB_CONTAINER" psql -U "$DB_USER" -d "$DB_NAME" -c "
        SELECT
            p.pid,
            EXTRACT(EPOCH FROM (now() - a.xact_start))::int AS duration_seconds,
            p.relid::regclass AS table,
            p.phase,
            ROUND(100.0 * p.heap_blks_scanned / p.heap_blks_total, 1) AS scanned_pct,
            ROUND(100.0 * p.heap_blks_vacuumed / p.heap_blks_total, 1) AS vacuumed_pct
        FROM pg_stat_progress_vacuum p
        JOIN pg_stat_activity a USING (pid);
        "
    fi
}

configuracoes_importantes() {
    print_header "Configurações Importantes"
    docker exec "$DB_CONTAINER" psql -U "$DB_USER" -d "$DB_NAME" -c "
    SELECT
        name,
        setting,
        unit
    FROM pg_settings
    WHERE name IN (
        'max_connections',
        'shared_buffers',
        'effective_cache_size',
        'work_mem',
        'maintenance_work_mem',
        'statement_timeout',
        'lock_timeout',
        'autovacuum_max_workers'
    )
    ORDER BY name;
    "
}

top_queries_lentas() {
    print_header "Top Queries Lentas (pg_stat_statements)"
    docker exec "$DB_CONTAINER" psql -U "$DB_USER" -d "$DB_NAME" -c "
    SELECT
        calls,
        ROUND(total_exec_time::numeric, 2) AS total_time_ms,
        ROUND(mean_exec_time::numeric, 2) AS avg_time_ms,
        ROUND(max_exec_time::numeric, 2) AS max_time_ms,
        LEFT(query, 80) AS query
    FROM pg_stat_statements
    WHERE dbid = (SELECT oid FROM pg_database WHERE datname = '$DB_NAME')
    ORDER BY total_exec_time DESC
    LIMIT 10;
    " 2>/dev/null || echo "pg_stat_statements não está habilitado"
}

monitoramento_continuo() {
    print_header "Monitoramento Contínuo (Ctrl+C para parar)"

    while true; do
        clear
        echo -e "${BLUE}=== Status Geral ===${NC}"

        # Conexões
        total_conn=$(run_query "SELECT count(*) FROM pg_stat_activity;")
        active_conn=$(run_query "SELECT count(*) FROM pg_stat_activity WHERE state = 'active';")
        max_conn=$(run_query "SELECT setting FROM pg_settings WHERE name = 'max_connections';")
        echo "Conexões: $total_conn / $max_conn (ativas: $active_conn)"

        # Queries lentas
        slow_count=$(run_query "SELECT count(*) FROM pg_stat_activity WHERE state != 'idle' AND now() - query_start > interval '5 seconds';")
        if [ "$slow_count" -gt 0 ]; then
            print_warning "Queries lentas: $slow_count"
        else
            echo "Queries lentas: 0"
        fi

        # Locks
        locks_count=$(run_query "SELECT count(*) FROM pg_locks WHERE NOT granted;")
        if [ "$locks_count" -gt 0 ]; then
            print_warning "Locks bloqueando: $locks_count"
        else
            echo "Locks bloqueando: 0"
        fi

        # Autovacuum
        vacuum_count=$(run_query "SELECT count(*) FROM pg_stat_progress_vacuum;")
        if [ "$vacuum_count" -gt 0 ]; then
            echo "Autovacuum rodando: $vacuum_count"
        else
            echo "Autovacuum rodando: 0"
        fi

        # Cache hit ratio
        ratio=$(run_query "SELECT ROUND(sum(heap_blks_hit)::numeric / NULLIF(sum(heap_blks_hit) + sum(heap_blks_read), 0), 4) FROM pg_statio_user_tables;")
        echo "Cache hit ratio: $ratio"

        echo -e "\n${BLUE}=== Top 5 Tabelas por Dead Rows ===${NC}"
        docker exec "$DB_CONTAINER" psql -U "$DB_USER" -d "$DB_NAME" -t -c "
        SELECT relname || ': ' || n_dead_tup || ' dead rows'
        FROM pg_stat_user_tables
        WHERE schemaname = 'public'
        ORDER BY n_dead_tup DESC
        LIMIT 5;
        "

        echo -e "\n${BLUE}Atualizado em: $(date '+%Y-%m-%d %H:%M:%S')${NC}"
        echo "Próxima atualização em 5 segundos..."
        sleep 5
    done
}

# Loop principal
while true; do
    show_menu

    case $choice in
        1) status_geral ;;
        2) conexoes_ativas ;;
        3) queries_lentas ;;
        4) locks_bloqueando ;;
        5) tamanho_tabelas ;;
        6) estatisticas_vacuum ;;
        7) cache_hit_ratio ;;
        8) autovacuum_progresso ;;
        9) configuracoes_importantes ;;
        10) top_queries_lentas ;;
        11) monitoramento_continuo ;;
        0) exit 0 ;;
        *) print_error "Opção inválida!" ;;
    esac

    echo ""
    read -p "Pressione ENTER para continuar..."
done
