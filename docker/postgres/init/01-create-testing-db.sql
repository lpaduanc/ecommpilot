-- ==========================================================================
-- PostgreSQL Initialization Script - EcommPilot
-- ==========================================================================

-- Habilitar extensão pgvector no banco principal
CREATE EXTENSION IF NOT EXISTS vector;

-- Habilitar pg_stat_statements para performance monitoring
CREATE EXTENSION IF NOT EXISTS pg_stat_statements;

-- Create testing database for PHPUnit tests
CREATE DATABASE laravel_testing;

-- Grant privileges to postgres user
GRANT ALL PRIVILEGES ON DATABASE laravel_testing TO postgres;

-- Conectar ao banco de testes e habilitar extensões
\c laravel_testing;
CREATE EXTENSION IF NOT EXISTS vector;
CREATE EXTENSION IF NOT EXISTS pg_stat_statements;

-- Conectar de volta ao banco principal
\c laravel;

-- Configurações adicionais de performance
-- Aumenta work_mem para esta sessão (para operações bulk)
ALTER DATABASE laravel SET work_mem = '32MB';
ALTER DATABASE laravel SET maintenance_work_mem = '256MB';

-- Otimiza planner para bulk operations
ALTER DATABASE laravel SET random_page_cost = 1.1;
ALTER DATABASE laravel SET effective_io_concurrency = 200;

-- Configurações de timeout consistentes com postgresql.conf
ALTER DATABASE laravel SET statement_timeout = '600000'; -- 10 min
ALTER DATABASE laravel SET idle_in_transaction_session_timeout = '300000'; -- 5 min
ALTER DATABASE laravel SET lock_timeout = '60000'; -- 1 min
