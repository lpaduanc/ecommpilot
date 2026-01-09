# Product Analytics API Documentation

## Endpoint: GET /api/products

Retorna a lista de produtos com dados de analytics enriquecidos.

### Parâmetros de Query

- `search` (string, opcional): Busca por nome ou SKU do produto
- `status` (string, opcional): Filtra por status de estoque (`low_stock`, `out_of_stock`)
- `per_page` (integer, opcional): Número de itens por página (padrão: 20)
- `page` (integer, opcional): Página atual (padrão: 1)

### Estrutura da Resposta

```json
{
  "data": [
    {
      "id": 1,
      "external_id": "12345",
      "name": "Nome do Produto",
      "description": "Descrição do produto",
      "price": 99.90,
      "compare_at_price": 149.90,
      "stock_quantity": 50,
      "sku": "PROD-001",
      "images": ["https://..."],
      "categories": ["Categoria 1"],
      "variants": [],
      "is_active": true,
      "has_low_stock": false,
      "is_out_of_stock": false,
      "has_discount": true,
      "discount_percentage": 33.37,
      "external_created_at": "2024-01-01T00:00:00.000000Z",
      "external_updated_at": "2024-01-15T00:00:00.000000Z",
      "analytics": {
        "name": "Nome do Produto",
        "classification": "Estrela",
        "abc_category": "A",
        "stock_health": "Alto",
        "sessions": 0,
        "units_sold": 150,
        "conversion_rate": 0,
        "total_sold": 14985.00,
        "sales_percentage": 25.50,
        "total_profit": 14985.00,
        "average_price": 99.90,
        "cost": 0,
        "margin": 100.00,
        "stock_quantity": 50
      }
    }
  ],
  "total": 100,
  "last_page": 5,
  "current_page": 1,
  "totals": {
    "total_products": 100,
    "total_sessions": 0,
    "total_units_sold": 1500,
    "total_revenue": 149850.00,
    "total_profit": 149850.00,
    "avg_conversion_rate": 0,
    "avg_margin": 100.00
  },
  "abc_analysis": {
    "category_a": {
      "count": 20,
      "percentage": 20.00
    },
    "category_b": {
      "count": 30,
      "percentage": 30.00
    },
    "category_c": {
      "count": 50,
      "percentage": 50.00
    }
  }
}
```

### Campos de Analytics

#### Classificação do Produto (`classification`)

Baseado na matriz BCG adaptada para e-commerce:

- **Estrela**: Alta conversão + Alto volume de vendas
- **Vaca Leiteira**: Baixa conversão + Alto volume de vendas
- **Interrogação**: Alta conversão + Baixo volume de vendas
- **Abacaxi**: Baixa conversão + Baixo volume de vendas

#### Categoria ABC (`abc_category`)

Baseado na curva ABC de faturamento:

- **A**: Produtos que representam ~80% do faturamento
- **B**: Produtos que representam ~15% do faturamento
- **C**: Produtos que representam ~5% do faturamento

#### Saúde do Estoque (`stock_health`)

Baseado na velocidade de vendas (últimos 30 dias):

- **Alto**: Estoque > 80 dias de vendas
- **Médio**: Estoque entre 30-80 dias de vendas
- **Baixo**: Estoque entre 10-30 dias de vendas
- **Crítico**: Estoque < 10 dias de vendas

#### Métricas Calculadas

- `sessions`: Número de sessões/visitas (atualmente 0, aguardando integração com Nuvemshop Analytics API)
- `units_sold`: Total de unidades vendidas
- `conversion_rate`: Taxa de conversão (units_sold / sessions * 100)
- `total_sold`: Valor total vendido em R$
- `sales_percentage`: Percentual das vendas totais da loja
- `total_profit`: Lucro total (total_sold - custo total)
- `average_price`: Preço médio de venda real
- `cost`: Custo do produto (atualmente 0, necessita campo adicional)
- `margin`: Margem de lucro em % ((total_sold - custo) / total_sold * 100)

### Observações

1. **Performance**: A análise é calculada sobre todos os produtos antes da paginação para garantir que a curva ABC e classificações sejam consistentes.

2. **Período de Análise**: Todas as métricas são calculadas com base em pedidos pagos (status `paid`) sem limite de tempo.

3. **Sessions**: Aguardando integração com Nuvemshop Analytics API. Atualmente retorna 0.

4. **Cost**: Necessita adicionar campo `cost` na tabela `synced_products`. Atualmente retorna 0.

5. **Matching de Produtos**: Produtos são identificados nos pedidos por `external_id` ou `sku`.

### Próximas Melhorias

- [ ] Adicionar campo `cost` na tabela `synced_products`
- [ ] Integrar com Nuvemshop Analytics API para obter dados de sessões
- [ ] Adicionar cache para melhorar performance
- [ ] Adicionar filtro por categoria ABC e classificação
- [ ] Adicionar ordenação por métricas de analytics
