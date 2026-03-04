# Segmentação RFM — Definição dos Segmentos

## O que é RFM?

RFM é uma metodologia de segmentação de clientes baseada em 3 dimensões de comportamento de compra:

- **R (Recência):** Há quanto tempo o cliente fez a última compra
- **F (Frequência):** Quantas vezes o cliente comprou
- **M (Monetário):** Quanto o cliente gastou no total

## Como os Scores são Calculados

Cada dimensão recebe um score de **1 a 5**, calculado por **quintis** (divisão em 5 faixas iguais) da base de clientes da loja:

| Score | R (Recência) | F (Frequência) | M (Monetário) |
|-------|-------------|----------------|---------------|
| **5** | Comprou muito recentemente | Compra com muita frequência | Gasta muito |
| **4** | Comprou recentemente | Compra com frequência | Gasta bastante |
| **3** | Comprou há algum tempo | Frequência moderada | Gasto moderado |
| **2** | Comprou há bastante tempo | Compra pouco | Gasta pouco |
| **1** | Faz muito tempo que não compra | Comprou poucas vezes | Gastou muito pouco |

> **Importante:** Os scores são **relativos à própria base da loja**. Um "Campeão" numa loja pequena pode gastar R$500, numa loja grande pode gastar R$50.000. Isso torna a segmentação justa independente do porte do negócio.

## Definição dos Segmentos

| Segmento | R | F | M | Descrição |
|----------|---|---|---|-----------|
| **Campeões** | 4-5 | 4-5 | 4-5 | Comprou recentemente, compra sempre e gasta muito. São os melhores clientes da loja. |
| **Clientes Fiéis** | 3-5 | 3-5 | 3-5 | Parecidos com campeões mas com scores um pouco menores. Compram com regularidade e gastam bem. |
| **Não Pode Perder** | 1 | 4-5 | 4-5 | Frequência e gasto altíssimos, mas faz tempo que não compram. Eram top clientes e sumiram — precisam ser reativados com urgência. |
| **Em Risco** | 1-2 | 3-5 | 3-5 | Compravam bastante e gastavam bem, mas estão sumindo. Um degrau abaixo do "Não Pode Perder". |
| **Potenciais Fiéis** | 4-5 | 2-3 | 2-3 | Compraram recentemente com frequência e gasto moderados. Com o incentivo certo, podem virar Fiéis ou Campeões. |
| **Novos Clientes** | 4-5 | 1 | 1-2 | Chegaram agora — primeira compra recente, gasto baixo ainda. Momento ideal para causar boa impressão e fidelizar. |
| **Promissores** | 3-4 | 1-2 | 1-2 | Compra relativamente recente, mas pouca frequência e gasto. Precisam de um empurrão para engajar. |
| **Precisam de Atenção** | 2-3 | 2-3 | 2-3 | Todos os scores medianos. Não estão indo nem para frente nem para trás — precisam de estímulo para não decaírem. |
| **Quase Dormindo** | 2-3 | 1-2 | 1-2 | Já faz um tempo que não compram e quando compravam era pouco. Estão esfriando. |
| **Hibernando** | 1-2 | 1-2 | 1-2 | Todos os scores baixos. Compraram pouco, gastaram pouco e faz tempo. Difícil reativar mas não impossível. |
| **Perdidos** | 1 | 1 | 1 | Piores scores em tudo, ou nunca fizeram pedido. Custo de reativação geralmente não compensa. |

## Ordem de Prioridade na Avaliação

A ordem em que os segmentos são avaliados importa — segmentos mais restritivos são verificados primeiro. Se um cliente se encaixa em mais de uma definição, ele é classificado pelo primeiro match:

1. Campeões
2. Clientes Fiéis
3. Não Pode Perder
4. Em Risco
5. Potenciais Fiéis
6. Novos Clientes
7. Promissores
8. Precisam de Atenção
9. Quase Dormindo
10. Hibernando
11. Perdidos

## Implementação Técnica

- **Arquivo:** `app/Services/CustomerRfmService.php`
- **Cache:** Resultados são cacheados por 6 horas (`CACHE_TTL_HOURS = 6`)
- **Invalidação:** O cache é invalidado automaticamente após o sync de clientes (`SyncCustomersJob`)
- **Cálculo de quintis:** Valores são ordenados e divididos nos percentis p20, p40, p60 e p80
- **Clientes sem pedidos:** Automaticamente classificados como "Perdidos" com scores [1, 1, 1]
- **Fallback:** Se nenhum segmento corresponder, o cliente é classificado como "Hibernando"
