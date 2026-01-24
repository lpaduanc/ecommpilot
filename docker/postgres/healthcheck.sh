#!/bin/bash
# ==========================================================================
# PostgreSQL Health Check Script
# ==========================================================================
# Verifica se o PostgreSQL está pronto para aceitar conexões e processar queries
# ==========================================================================

set -eo pipefail

# Configurações
DB_USER="${POSTGRES_USER:-postgres}"
DB_NAME="${POSTGRES_DB:-laravel}"
MAX_CONNECTIONS_THRESHOLD=250  # Alerta se > 80% de max_connections (300)

# 1. Verifica se o servidor está aceitando conexões
if ! pg_isready -U "$DB_USER" -d "$DB_NAME" -q; then
    echo "PostgreSQL not ready"
    exit 1
fi

# 2. Verifica se consegue executar uma query simples
if ! psql -U "$DB_USER" -d "$DB_NAME" -tAc "SELECT 1;" > /dev/null 2>&1; then
    echo "PostgreSQL cannot execute queries"
    exit 1
fi

# 3. Verifica número de conexões ativas (warning, mas não falha)
ACTIVE_CONNECTIONS=$(psql -U "$DB_USER" -d "$DB_NAME" -tAc "SELECT count(*) FROM pg_stat_activity WHERE state = 'active';")
if [ "$ACTIVE_CONNECTIONS" -gt "$MAX_CONNECTIONS_THRESHOLD" ]; then
    echo "WARNING: High number of active connections: $ACTIVE_CONNECTIONS"
    # Não falha o health check, apenas alerta
fi

# 4. Verifica se há locks de longa duração (> 5 minutos)
LONG_LOCKS=$(psql -U "$DB_USER" -d "$DB_NAME" -tAc "SELECT count(*) FROM pg_locks WHERE NOT granted AND age(now(), query_start) > interval '5 minutes';")
if [ "$LONG_LOCKS" -gt "0" ]; then
    echo "WARNING: Found $LONG_LOCKS long-running locks"
    # Não falha o health check, apenas alerta
fi

echo "PostgreSQL healthy"
exit 0
