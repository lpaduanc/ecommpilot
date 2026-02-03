# Resumo do Pipeline de Análise - EcommPilot

## Visão Geral do Pipeline

```
Store Data → Collector → Analyst → Strategist → Critic → Suggestions
                ↓                                    ↓
         [RAG: Benchmarks]                    [Memory: Histórico]
         [External: Trends, Market, Competitors]
```

---

## Versão dos Prompts

**Versão Atual:** V5 (Todos os agentes refatorados)

**Mudanças da V5:**
- Removidas personas fictícias
- Adicionados few-shot examples concretos
- Prompts reduzidos (~40-50%)
- Formato de saída simplificado
- Constraints específicos e mensuráveis
- **NOVO:** Exclusão de brindes/amostras grátis dos dados de estoque

---

## Dados Utilizados na Análise

### 1. Dados Internos da Loja
- **Pedidos**: Últimos 15 dias de pedidos com status, valores, métodos de pagamento
- **Produtos**: Catálogo completo com preços, estoque, vendas
- **Cupons**: Cupons ativos, uso, impacto no ticket
- **Histórico**: Análises anteriores e sugestões já dadas
- **Filtros**: Brindes/amostras grátis são EXCLUÍDOS automaticamente dos dados de estoque

### 2. Dados Externos de Mercado (Real-time)

#### Google Trends
- **Fonte**: SerpAPI / Google Trends
- **Dados coletados**: Tendência (alta/estável/queda), interesse de busca (0-100)
- **Uso**: Identificar momento do mercado e oportunidades

#### Preços de Mercado (Google Shopping)
- **Fonte**: SerpAPI / Google Shopping
- **Dados coletados**: Preço mínimo, máximo e médio de produtos similares
- **Uso**: Posicionamento competitivo de preços

#### Análise de Concorrentes (Decodo Web Scraping)
- **Fonte**: Decodo Scraping API
- **Dados coletados**:
  - Faixa de preços (min, max, média)
  - Categorias populares com contagem de menções
  - Produtos em destaque com preços
  - Promoções ativas (descontos, campanhas)
  - Avaliações (nota média, total)
  - Diferenciais (frete grátis, fidelidade, etc.)
  - Tamanho estimado do catálogo
- **Uso**: Análise comparativa, identificação de gaps

### 3. RAG (Knowledge Base)
- **Benchmarks do setor**: Métricas de referência por nicho/subcategoria
- **Estratégias comprovadas**: Cases de sucesso e táticas recomendadas
- **Uso**: Fundamentar sugestões com dados de mercado

---

## Fluxo dos 4 Agentes

### 1. COLLECTOR AGENT (V5)
**Objetivo**: Coletar e organizar todos os dados disponíveis

**Entradas**:
- Dados da loja (estatísticas, histórico)
- Sugestões anteriores
- Benchmarks do setor
- Dados externos (Trends, Market, Competitors)

**Saídas**:
- Identificação da loja
- Resumo histórico (5-7 fatos com números)
- Padrões de sucesso/fracasso
- Sugestões proibidas (para evitar repetição)
- Posicionamento de mercado (comparação tripla)
- Análise competitiva com dados ricos
- Alertas categorizados (critical, warnings, info)

**Few-Shot Examples:**
- Resumo histórico bem escrito
- Análise competitiva com dados ricos
- Alerta bem estruturado

---

### 2. ANALYST AGENT (V5)
**Objetivo**: Diagnosticar a saúde do negócio

**Entradas**:
- Contexto do Collector
- Dados operacionais detalhados
- Histórico da própria loja
- Dados de concorrentes e mercado

**Saídas**:
- Health Score (0-100) com override se necessário
- Alertas com severidade (crítico/atenção/monitorar)
- 5 Oportunidades com potencial em R$
- Posicionamento de mercado (tripla comparação)
- Anomalias vs histórico
- Briefing para o Strategist

**Nota Importante:** Os dados de estoque EXCLUEM produtos que são brindes/amostras grátis.

**Few-Shot Examples:**
- Alerta crítico bem escrito
- Oportunidade bem escrita
- Health Score com override

---

### 3. STRATEGIST AGENT (V5)
**Objetivo**: Gerar 9 sugestões estratégicas originais

**Entradas**:
- Contexto do Collector
- Análise do Analyst
- Sugestões anteriores (para evitar repetição)
- Sugestões aceitas/rejeitadas
- Dados de concorrentes e mercado

**Saídas**:
- 9 sugestões (3 HIGH, 3 MEDIUM, 3 LOW)
- Cada sugestão com:
  - Problema específico com dados
  - Ação em passos numerados
  - Resultado esperado com número (R$ ou %)
  - Fonte do dado
  - Implementação (tipo, app, complexidade, custo)

**Nota Importante:** Não criar sugestões de reposição para produtos gratuitos (brindes).

**Few-Shot Examples:**
- HIGH com dado específico
- MEDIUM com otimização baseada em análise
- LOW quick win simples

---

### 4. CRITIC AGENT (V5)
**Objetivo**: Revisar e garantir qualidade das sugestões

**Entradas**:
- 9 sugestões do Strategist
- Contexto da loja
- Sugestões anteriores

**Saídas**:
- Sugestões aprovadas/melhoradas/substituídas
- Review summary (approved, improved, rejected)
- Validação de distribuição 3-3-3

**Decisões:**
- APROVAR: tem dado específico + ação clara + resultado com número
- MELHORAR: corrigir o que falta e aprovar
- REJEITAR: repetição ou impossível → criar substituta

**Few-Shot Examples:**
- Aprovar (sugestão já está boa)
- Melhorar (falta dado específico)
- Rejeitar (tema já sugerido)
- Rejeitar (impossível na plataforma)

---

## Prompts Auxiliares

### SIMILARITY CHECK PROMPT (V5)
**Objetivo**: Detectar duplicatas e gerar "zonas proibidas"

**Entradas**:
- Lista de sugestões anteriores

**Saídas**:
- Zonas proibidas (por sugestão com variações)
- Abordagens ainda válidas por categoria
- Coverage summary
- Guidance para o Strategist

### LITE ANALYST PROMPT (V5)
**Objetivo**: Análise rápida otimizada para limites de tokens

**Saídas**:
- Métricas resumidas
- Máximo 3 anomalias
- Health score compacto

**Nota:** Inclui informação sobre brindes filtrados.

### LITE STRATEGIST PROMPT (V5)
**Objetivo**: Sugestões rápidas (6 ao invés de 9)

**Saídas**:
- 6 sugestões (2 HIGH, 2 MEDIUM, 2 LOW)
- Formato simplificado com exemplo

---

## Exemplo de Análise (ID: 10)

### Loja Analisada
- **Nome**: [Nome da Loja em Análise]
- **Nicho**: beauty / haircare
- **Plataforma**: Nuvemshop

### Dados Externos Coletados
| Fonte | Status | Dados |
|-------|--------|-------|
| Google Trends | ✅ Sucesso | Tendência: queda, Interesse: 60/100 |
| Google Shopping | ✅ Sucesso | Preço médio: R$ 49,40 |
| Concorrentes (Decodo) | ✅ 4/4 analisados | Com dados ricos |

### Concorrentes Analisados
1. **Hidratei** - Preço médio: R$ 282,23 | Diferenciais: vegano, cruelty_free, quiz
2. **Cadiveu** - Preço médio: R$ 106,95 | Diferenciais: frete grátis, fidelidade
3. **Ge Beauty** - Preço médio: R$ 127,47 | Diferenciais: primeira compra, quiz
4. **Belletonn** - Preço médio: R$ 146,71 | Diferenciais: vegano, outlet

### RAG/Benchmarks
- 5 benchmarks carregados
- 5 estratégias do Knowledge Base

### Histórico
- 3 análises anteriores
- 27 sugestões anteriores

### Resultado
- Health Score: 50 (Atenção)
- 9 sugestões geradas e validadas

---

## Arquivos de Prompts

1. `01_collector_agent_prompt.md` - Coleta e organização de dados (V5)
2. `02_analyst_agent_prompt.md` - Diagnóstico completo da loja (V5)
3. `03_strategist_agent_prompt.md` - Geração de sugestões originais (V5)
4. `04_critic_agent_prompt.md` - Revisão e garantia de qualidade (V5)
5. `05_similarity_check_prompt.md` - Detecção de duplicatas (V5)
6. `06_lite_analyst_prompt.md` - Análise rápida (V5)
7. `07_lite_strategist_prompt.md` - Sugestões rápidas (V5)

---

## Tecnologias Utilizadas

- **AI Provider**: Google Gemini 2.5 Flash
- **Web Scraping**: Decodo API
- **Market Data**: SerpAPI (Google Trends + Google Shopping)
- **Knowledge Base**: RAG com embeddings
- **Plataforma**: Laravel 12 + Vue 3

