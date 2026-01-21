<?php

/**
 * Benchmarks de e-commerce por nicho e subcategoria.
 *
 * Fontes: ABComm, Neotrust, NuvemCommerce, Circana, NIQ (2024)
 *
 * Estrutura:
 * - default: benchmarks padrão do nicho
 * - subcategories: benchmarks específicos por subcategoria
 *
 * Cada benchmark contém:
 * - ticket_medio: [min, max, media] em R$
 * - taxa_conversao: [desktop, mobile, geral] em %
 */

return [
    'beauty' => [
        'default' => [
            'ticket_medio' => ['min' => 180, 'max' => 280, 'media' => 224],
            'taxa_conversao' => ['desktop' => 1.0, 'mobile' => 0.5, 'geral' => 0.8],
        ],
        'subcategories' => [
            'haircare' => [
                'ticket_medio' => ['min' => 90, 'max' => 180, 'media' => 135],
                'taxa_conversao' => ['desktop' => 1.2, 'mobile' => 0.6, 'geral' => 0.9],
                'caracteristicas' => 'Compra recorrente, alto LTV, kits de tratamento',
            ],
            'skincare' => [
                'ticket_medio' => ['min' => 150, 'max' => 350, 'media' => 250],
                'taxa_conversao' => ['desktop' => 1.0, 'mobile' => 0.5, 'geral' => 0.8],
                'caracteristicas' => 'Produtos premium, kits de rotina, alto ticket',
            ],
            'maquiagem' => [
                'ticket_medio' => ['min' => 120, 'max' => 300, 'media' => 180],
                'taxa_conversao' => ['desktop' => 1.1, 'mobile' => 0.6, 'geral' => 0.85],
                'caracteristicas' => 'Impulso + planejada, paletas, kits',
            ],
            'perfumaria' => [
                'ticket_medio' => ['min' => 200, 'max' => 500, 'media' => 320],
                'taxa_conversao' => ['desktop' => 0.8, 'mobile' => 0.4, 'geral' => 0.6],
                'caracteristicas' => 'Alto ticket, presenteável, sazonalidade forte',
            ],
            'corpo_banho' => [
                'ticket_medio' => ['min' => 80, 'max' => 160, 'media' => 120],
                'taxa_conversao' => ['desktop' => 1.3, 'mobile' => 0.7, 'geral' => 1.0],
                'caracteristicas' => 'Recorrência alta, ticket menor, volume',
            ],
            'geral' => [
                'ticket_medio' => ['min' => 180, 'max' => 280, 'media' => 224],
                'taxa_conversao' => ['desktop' => 1.0, 'mobile' => 0.5, 'geral' => 0.8],
                'caracteristicas' => 'Mix de produtos de beleza',
            ],
        ],
    ],

    'moda' => [
        'default' => [
            'ticket_medio' => ['min' => 200, 'max' => 320, 'media' => 260],
            'taxa_conversao' => ['desktop' => 1.5, 'mobile' => 0.8, 'geral' => 1.2],
        ],
        'subcategories' => [
            'feminino' => [
                'ticket_medio' => ['min' => 180, 'max' => 320, 'media' => 250],
                'taxa_conversao' => ['desktop' => 1.6, 'mobile' => 0.9, 'geral' => 1.3],
                'caracteristicas' => 'Alta frequência, sazonalidade, promoções',
            ],
            'masculino' => [
                'ticket_medio' => ['min' => 200, 'max' => 380, 'media' => 280],
                'taxa_conversao' => ['desktop' => 1.4, 'mobile' => 0.7, 'geral' => 1.1],
                'caracteristicas' => 'Menor frequência, maior ticket unitário',
            ],
            'infantil' => [
                'ticket_medio' => ['min' => 120, 'max' => 220, 'media' => 170],
                'taxa_conversao' => ['desktop' => 1.5, 'mobile' => 0.8, 'geral' => 1.2],
                'caracteristicas' => 'Recorrência por crescimento, presentes',
            ],
            'calcados' => [
                'ticket_medio' => ['min' => 180, 'max' => 350, 'media' => 260],
                'taxa_conversao' => ['desktop' => 1.3, 'mobile' => 0.7, 'geral' => 1.0],
                'caracteristicas' => 'Alto ticket, necessidade de ajuste',
            ],
            'acessorios' => [
                'ticket_medio' => ['min' => 80, 'max' => 200, 'media' => 140],
                'taxa_conversao' => ['desktop' => 1.8, 'mobile' => 1.0, 'geral' => 1.4],
                'caracteristicas' => 'Compra por impulso, cross-sell',
            ],
            'intima' => [
                'ticket_medio' => ['min' => 100, 'max' => 200, 'media' => 150],
                'taxa_conversao' => ['desktop' => 1.4, 'mobile' => 0.8, 'geral' => 1.1],
                'caracteristicas' => 'Recorrência, kits, promoções',
            ],
            'praia' => [
                'ticket_medio' => ['min' => 120, 'max' => 280, 'media' => 200],
                'taxa_conversao' => ['desktop' => 1.5, 'mobile' => 0.9, 'geral' => 1.2],
                'caracteristicas' => 'Forte sazonalidade verão',
            ],
            'geral' => [
                'ticket_medio' => ['min' => 200, 'max' => 320, 'media' => 260],
                'taxa_conversao' => ['desktop' => 1.5, 'mobile' => 0.8, 'geral' => 1.2],
                'caracteristicas' => 'Mix de moda e vestuário',
            ],
        ],
    ],

    'eletronicos' => [
        'default' => [
            'ticket_medio' => ['min' => 400, 'max' => 700, 'media' => 522],
            'taxa_conversao' => ['desktop' => 1.2, 'mobile' => 0.5, 'geral' => 0.9],
        ],
        'subcategories' => [
            'smartphones' => [
                'ticket_medio' => ['min' => 1200, 'max' => 3500, 'media' => 2200],
                'taxa_conversao' => ['desktop' => 0.8, 'mobile' => 0.4, 'geral' => 0.6],
                'caracteristicas' => 'Alto ticket, pesquisa longa, comparação',
            ],
            'informatica' => [
                'ticket_medio' => ['min' => 800, 'max' => 3000, 'media' => 1800],
                'taxa_conversao' => ['desktop' => 1.0, 'mobile' => 0.4, 'geral' => 0.7],
                'caracteristicas' => 'Compra planejada, especificações técnicas',
            ],
            'games' => [
                'ticket_medio' => ['min' => 300, 'max' => 800, 'media' => 500],
                'taxa_conversao' => ['desktop' => 1.4, 'mobile' => 0.6, 'geral' => 1.0],
                'caracteristicas' => 'Lançamentos, pré-vendas, comunidade',
            ],
            'audio_video' => [
                'ticket_medio' => ['min' => 400, 'max' => 1500, 'media' => 800],
                'taxa_conversao' => ['desktop' => 1.1, 'mobile' => 0.5, 'geral' => 0.8],
                'caracteristicas' => 'Fones, TVs, soundbars',
            ],
            'acessorios' => [
                'ticket_medio' => ['min' => 80, 'max' => 250, 'media' => 150],
                'taxa_conversao' => ['desktop' => 1.8, 'mobile' => 1.0, 'geral' => 1.4],
                'caracteristicas' => 'Cross-sell, impulso, recorrência',
            ],
            'geral' => [
                'ticket_medio' => ['min' => 400, 'max' => 700, 'media' => 522],
                'taxa_conversao' => ['desktop' => 1.2, 'mobile' => 0.5, 'geral' => 0.9],
                'caracteristicas' => 'Mix de eletrônicos',
            ],
        ],
    ],

    'casa_decoracao' => [
        'default' => [
            'ticket_medio' => ['min' => 200, 'max' => 500, 'media' => 320],
            'taxa_conversao' => ['desktop' => 1.0, 'mobile' => 0.5, 'geral' => 0.8],
        ],
        'subcategories' => [
            'moveis' => [
                'ticket_medio' => ['min' => 400, 'max' => 1500, 'media' => 800],
                'taxa_conversao' => ['desktop' => 0.7, 'mobile' => 0.3, 'geral' => 0.5],
                'caracteristicas' => 'Alto ticket, pesquisa longa, frete complexo',
            ],
            'decoracao' => [
                'ticket_medio' => ['min' => 100, 'max' => 400, 'media' => 220],
                'taxa_conversao' => ['desktop' => 1.2, 'mobile' => 0.6, 'geral' => 0.9],
                'caracteristicas' => 'Impulso, tendências, sazonalidade',
            ],
            'cama_mesa_banho' => [
                'ticket_medio' => ['min' => 150, 'max' => 400, 'media' => 260],
                'taxa_conversao' => ['desktop' => 1.3, 'mobile' => 0.7, 'geral' => 1.0],
                'caracteristicas' => 'Kits, promoções, recorrência',
            ],
            'utilidades' => [
                'ticket_medio' => ['min' => 80, 'max' => 250, 'media' => 150],
                'taxa_conversao' => ['desktop' => 1.5, 'mobile' => 0.8, 'geral' => 1.2],
                'caracteristicas' => 'Necessidade, cross-sell',
            ],
            'jardim' => [
                'ticket_medio' => ['min' => 150, 'max' => 500, 'media' => 300],
                'taxa_conversao' => ['desktop' => 1.0, 'mobile' => 0.5, 'geral' => 0.8],
                'caracteristicas' => 'Sazonalidade, projetos',
            ],
            'iluminacao' => [
                'ticket_medio' => ['min' => 100, 'max' => 350, 'media' => 200],
                'taxa_conversao' => ['desktop' => 1.1, 'mobile' => 0.6, 'geral' => 0.9],
                'caracteristicas' => 'Projetos, reforma, decoração',
            ],
            'geral' => [
                'ticket_medio' => ['min' => 200, 'max' => 500, 'media' => 320],
                'taxa_conversao' => ['desktop' => 1.0, 'mobile' => 0.5, 'geral' => 0.8],
                'caracteristicas' => 'Mix de casa e decoração',
            ],
        ],
    ],

    'alimentos' => [
        'default' => [
            'ticket_medio' => ['min' => 100, 'max' => 250, 'media' => 170],
            'taxa_conversao' => ['desktop' => 1.8, 'mobile' => 1.0, 'geral' => 1.4],
        ],
        'subcategories' => [
            'gourmet' => [
                'ticket_medio' => ['min' => 150, 'max' => 400, 'media' => 250],
                'taxa_conversao' => ['desktop' => 1.2, 'mobile' => 0.6, 'geral' => 0.9],
                'caracteristicas' => 'Premium, importados, presentes',
            ],
            'saudaveis' => [
                'ticket_medio' => ['min' => 120, 'max' => 300, 'media' => 200],
                'taxa_conversao' => ['desktop' => 1.5, 'mobile' => 0.8, 'geral' => 1.2],
                'caracteristicas' => 'Orgânicos, fit, recorrência',
            ],
            'bebidas' => [
                'ticket_medio' => ['min' => 100, 'max' => 350, 'media' => 180],
                'taxa_conversao' => ['desktop' => 1.4, 'mobile' => 0.7, 'geral' => 1.1],
                'caracteristicas' => 'Vinhos, cafés especiais, volume',
            ],
            'doces' => [
                'ticket_medio' => ['min' => 80, 'max' => 200, 'media' => 130],
                'taxa_conversao' => ['desktop' => 2.0, 'mobile' => 1.2, 'geral' => 1.6],
                'caracteristicas' => 'Impulso, presentes, sazonalidade',
            ],
            'geral' => [
                'ticket_medio' => ['min' => 100, 'max' => 250, 'media' => 170],
                'taxa_conversao' => ['desktop' => 1.8, 'mobile' => 1.0, 'geral' => 1.4],
                'caracteristicas' => 'Mix de alimentos',
            ],
        ],
    ],

    'pet' => [
        'default' => [
            'ticket_medio' => ['min' => 120, 'max' => 200, 'media' => 155],
            'taxa_conversao' => ['desktop' => 1.6, 'mobile' => 0.9, 'geral' => 1.3],
        ],
        'subcategories' => [
            'racao' => [
                'ticket_medio' => ['min' => 120, 'max' => 250, 'media' => 180],
                'taxa_conversao' => ['desktop' => 2.0, 'mobile' => 1.2, 'geral' => 1.6],
                'caracteristicas' => 'Alta recorrência, assinatura, premium',
            ],
            'acessorios' => [
                'ticket_medio' => ['min' => 60, 'max' => 150, 'media' => 100],
                'taxa_conversao' => ['desktop' => 1.5, 'mobile' => 0.8, 'geral' => 1.2],
                'caracteristicas' => 'Impulso, cross-sell, brinquedos',
            ],
            'higiene' => [
                'ticket_medio' => ['min' => 50, 'max' => 120, 'media' => 80],
                'taxa_conversao' => ['desktop' => 1.8, 'mobile' => 1.0, 'geral' => 1.4],
                'caracteristicas' => 'Recorrência, kits',
            ],
            'medicamentos' => [
                'ticket_medio' => ['min' => 100, 'max' => 300, 'media' => 180],
                'taxa_conversao' => ['desktop' => 1.2, 'mobile' => 0.6, 'geral' => 0.9],
                'caracteristicas' => 'Necessidade, prescrição',
            ],
            'geral' => [
                'ticket_medio' => ['min' => 120, 'max' => 200, 'media' => 155],
                'taxa_conversao' => ['desktop' => 1.6, 'mobile' => 0.9, 'geral' => 1.3],
                'caracteristicas' => 'Mix de pet shop',
            ],
        ],
    ],

    'saude' => [
        'default' => [
            'ticket_medio' => ['min' => 180, 'max' => 300, 'media' => 224],
            'taxa_conversao' => ['desktop' => 1.4, 'mobile' => 0.7, 'geral' => 1.1],
        ],
        'subcategories' => [
            'suplementos' => [
                'ticket_medio' => ['min' => 150, 'max' => 350, 'media' => 250],
                'taxa_conversao' => ['desktop' => 1.6, 'mobile' => 0.9, 'geral' => 1.3],
                'caracteristicas' => 'Recorrência, whey, creatina, combos',
            ],
            'fitness' => [
                'ticket_medio' => ['min' => 200, 'max' => 500, 'media' => 300],
                'taxa_conversao' => ['desktop' => 1.2, 'mobile' => 0.6, 'geral' => 0.9],
                'caracteristicas' => 'Equipamentos, acessórios',
            ],
            'natural' => [
                'ticket_medio' => ['min' => 80, 'max' => 200, 'media' => 140],
                'taxa_conversao' => ['desktop' => 1.5, 'mobile' => 0.8, 'geral' => 1.2],
                'caracteristicas' => 'Fitoterápicos, chás, óleos',
            ],
            'farmacia' => [
                'ticket_medio' => ['min' => 100, 'max' => 250, 'media' => 170],
                'taxa_conversao' => ['desktop' => 1.8, 'mobile' => 1.0, 'geral' => 1.4],
                'caracteristicas' => 'Necessidade, OTC, recorrência',
            ],
            'ortopedicos' => [
                'ticket_medio' => ['min' => 150, 'max' => 400, 'media' => 250],
                'taxa_conversao' => ['desktop' => 1.0, 'mobile' => 0.5, 'geral' => 0.8],
                'caracteristicas' => 'Prescrição, necessidade',
            ],
            'geral' => [
                'ticket_medio' => ['min' => 180, 'max' => 300, 'media' => 224],
                'taxa_conversao' => ['desktop' => 1.4, 'mobile' => 0.7, 'geral' => 1.1],
                'caracteristicas' => 'Mix de saúde e bem-estar',
            ],
        ],
    ],

    'esportes' => [
        'default' => [
            'ticket_medio' => ['min' => 180, 'max' => 350, 'media' => 260],
            'taxa_conversao' => ['desktop' => 1.3, 'mobile' => 0.7, 'geral' => 1.0],
        ],
        'subcategories' => [
            'fitness' => [
                'ticket_medio' => ['min' => 200, 'max' => 400, 'media' => 280],
                'taxa_conversao' => ['desktop' => 1.4, 'mobile' => 0.8, 'geral' => 1.1],
                'caracteristicas' => 'Academia, crossfit, equipamentos',
            ],
            'outdoor' => [
                'ticket_medio' => ['min' => 250, 'max' => 600, 'media' => 400],
                'taxa_conversao' => ['desktop' => 1.0, 'mobile' => 0.5, 'geral' => 0.8],
                'caracteristicas' => 'Camping, trilha, alto ticket',
            ],
            'aquaticos' => [
                'ticket_medio' => ['min' => 150, 'max' => 400, 'media' => 250],
                'taxa_conversao' => ['desktop' => 1.2, 'mobile' => 0.6, 'geral' => 0.9],
                'caracteristicas' => 'Natação, surf, sazonalidade',
            ],
            'ciclismo' => [
                'ticket_medio' => ['min' => 300, 'max' => 800, 'media' => 500],
                'taxa_conversao' => ['desktop' => 1.0, 'mobile' => 0.5, 'geral' => 0.8],
                'caracteristicas' => 'Alto ticket, acessórios, comunidade',
            ],
            'futebol' => [
                'ticket_medio' => ['min' => 150, 'max' => 350, 'media' => 240],
                'taxa_conversao' => ['desktop' => 1.5, 'mobile' => 0.9, 'geral' => 1.2],
                'caracteristicas' => 'Camisas de time, chuteiras, bolas',
            ],
            'vestuario' => [
                'ticket_medio' => ['min' => 150, 'max' => 300, 'media' => 220],
                'taxa_conversao' => ['desktop' => 1.4, 'mobile' => 0.8, 'geral' => 1.1],
                'caracteristicas' => 'Roupas esportivas, leggings, tops',
            ],
            'geral' => [
                'ticket_medio' => ['min' => 180, 'max' => 350, 'media' => 260],
                'taxa_conversao' => ['desktop' => 1.3, 'mobile' => 0.7, 'geral' => 1.0],
                'caracteristicas' => 'Mix de esportes e lazer',
            ],
        ],
    ],

    'infantil' => [
        'default' => [
            'ticket_medio' => ['min' => 150, 'max' => 280, 'media' => 210],
            'taxa_conversao' => ['desktop' => 1.4, 'mobile' => 0.8, 'geral' => 1.1],
        ],
        'subcategories' => [
            'roupas' => [
                'ticket_medio' => ['min' => 120, 'max' => 250, 'media' => 180],
                'taxa_conversao' => ['desktop' => 1.5, 'mobile' => 0.9, 'geral' => 1.2],
                'caracteristicas' => 'Recorrência por crescimento, kits',
            ],
            'brinquedos' => [
                'ticket_medio' => ['min' => 100, 'max' => 300, 'media' => 180],
                'taxa_conversao' => ['desktop' => 1.3, 'mobile' => 0.7, 'geral' => 1.0],
                'caracteristicas' => 'Sazonalidade forte, presentes',
            ],
            'higiene' => [
                'ticket_medio' => ['min' => 100, 'max' => 200, 'media' => 150],
                'taxa_conversao' => ['desktop' => 1.8, 'mobile' => 1.0, 'geral' => 1.4],
                'caracteristicas' => 'Alta recorrência, fraldas, essenciais',
            ],
            'alimentacao' => [
                'ticket_medio' => ['min' => 80, 'max' => 180, 'media' => 130],
                'taxa_conversao' => ['desktop' => 2.0, 'mobile' => 1.2, 'geral' => 1.6],
                'caracteristicas' => 'Recorrência, fórmulas, papinhas',
            ],
            'moveis' => [
                'ticket_medio' => ['min' => 300, 'max' => 800, 'media' => 500],
                'taxa_conversao' => ['desktop' => 0.8, 'mobile' => 0.4, 'geral' => 0.6],
                'caracteristicas' => 'Alto ticket, berços, cômodas',
            ],
            'geral' => [
                'ticket_medio' => ['min' => 150, 'max' => 280, 'media' => 210],
                'taxa_conversao' => ['desktop' => 1.4, 'mobile' => 0.8, 'geral' => 1.1],
                'caracteristicas' => 'Mix de produtos infantis',
            ],
        ],
    ],

    'joias_relogios' => [
        'default' => [
            'ticket_medio' => ['min' => 200, 'max' => 600, 'media' => 380],
            'taxa_conversao' => ['desktop' => 0.8, 'mobile' => 0.4, 'geral' => 0.6],
        ],
        'subcategories' => [
            'joias' => [
                'ticket_medio' => ['min' => 500, 'max' => 2000, 'media' => 1000],
                'taxa_conversao' => ['desktop' => 0.5, 'mobile' => 0.2, 'geral' => 0.4],
                'caracteristicas' => 'Alto ticket, presentes, ocasiões especiais',
            ],
            'semi_joias' => [
                'ticket_medio' => ['min' => 150, 'max' => 400, 'media' => 250],
                'taxa_conversao' => ['desktop' => 1.0, 'mobile' => 0.5, 'geral' => 0.8],
                'caracteristicas' => 'Folheados, tendências, coleções',
            ],
            'relogios' => [
                'ticket_medio' => ['min' => 300, 'max' => 1500, 'media' => 700],
                'taxa_conversao' => ['desktop' => 0.7, 'mobile' => 0.3, 'geral' => 0.5],
                'caracteristicas' => 'Alto ticket, presentes, smartwatches',
            ],
            'bijuterias' => [
                'ticket_medio' => ['min' => 50, 'max' => 150, 'media' => 90],
                'taxa_conversao' => ['desktop' => 1.5, 'mobile' => 0.8, 'geral' => 1.2],
                'caracteristicas' => 'Impulso, tendências, volume',
            ],
            'geral' => [
                'ticket_medio' => ['min' => 200, 'max' => 600, 'media' => 380],
                'taxa_conversao' => ['desktop' => 0.8, 'mobile' => 0.4, 'geral' => 0.6],
                'caracteristicas' => 'Mix de joias e relógios',
            ],
        ],
    ],

    'papelaria' => [
        'default' => [
            'ticket_medio' => ['min' => 80, 'max' => 180, 'media' => 120],
            'taxa_conversao' => ['desktop' => 1.8, 'mobile' => 1.0, 'geral' => 1.4],
        ],
        'subcategories' => [
            'escolar' => [
                'ticket_medio' => ['min' => 100, 'max' => 250, 'media' => 160],
                'taxa_conversao' => ['desktop' => 1.6, 'mobile' => 0.9, 'geral' => 1.3],
                'caracteristicas' => 'Sazonalidade volta às aulas, kits',
            ],
            'escritorio' => [
                'ticket_medio' => ['min' => 80, 'max' => 200, 'media' => 130],
                'taxa_conversao' => ['desktop' => 1.5, 'mobile' => 0.8, 'geral' => 1.2],
                'caracteristicas' => 'B2B, recorrência, essenciais',
            ],
            'artesanato' => [
                'ticket_medio' => ['min' => 60, 'max' => 150, 'media' => 100],
                'taxa_conversao' => ['desktop' => 2.0, 'mobile' => 1.2, 'geral' => 1.6],
                'caracteristicas' => 'Nicho dedicado, DIY, comunidade',
            ],
            'presentes' => [
                'ticket_medio' => ['min' => 50, 'max' => 120, 'media' => 80],
                'taxa_conversao' => ['desktop' => 2.2, 'mobile' => 1.3, 'geral' => 1.8],
                'caracteristicas' => 'Embalagens, sazonalidade festas',
            ],
            'geral' => [
                'ticket_medio' => ['min' => 80, 'max' => 180, 'media' => 120],
                'taxa_conversao' => ['desktop' => 1.8, 'mobile' => 1.0, 'geral' => 1.4],
                'caracteristicas' => 'Mix de papelaria',
            ],
        ],
    ],

    'automotivo' => [
        'default' => [
            'ticket_medio' => ['min' => 150, 'max' => 400, 'media' => 260],
            'taxa_conversao' => ['desktop' => 1.2, 'mobile' => 0.6, 'geral' => 0.9],
        ],
        'subcategories' => [
            'acessorios' => [
                'ticket_medio' => ['min' => 80, 'max' => 250, 'media' => 150],
                'taxa_conversao' => ['desktop' => 1.4, 'mobile' => 0.7, 'geral' => 1.1],
                'caracteristicas' => 'Tapetes, capas, organizadores',
            ],
            'pecas' => [
                'ticket_medio' => ['min' => 200, 'max' => 600, 'media' => 350],
                'taxa_conversao' => ['desktop' => 1.0, 'mobile' => 0.5, 'geral' => 0.8],
                'caracteristicas' => 'Necessidade, manutenção',
            ],
            'som' => [
                'ticket_medio' => ['min' => 150, 'max' => 500, 'media' => 300],
                'taxa_conversao' => ['desktop' => 1.1, 'mobile' => 0.5, 'geral' => 0.8],
                'caracteristicas' => 'Alto-falantes, multimídia',
            ],
            'cuidados' => [
                'ticket_medio' => ['min' => 50, 'max' => 150, 'media' => 90],
                'taxa_conversao' => ['desktop' => 1.8, 'mobile' => 1.0, 'geral' => 1.4],
                'caracteristicas' => 'Limpeza, cera, recorrência',
            ],
            'geral' => [
                'ticket_medio' => ['min' => 150, 'max' => 400, 'media' => 260],
                'taxa_conversao' => ['desktop' => 1.2, 'mobile' => 0.6, 'geral' => 0.9],
                'caracteristicas' => 'Mix automotivo',
            ],
        ],
    ],

    'sex_shop' => [
        'default' => [
            'ticket_medio' => ['min' => 120, 'max' => 280, 'media' => 190],
            'taxa_conversao' => ['desktop' => 1.0, 'mobile' => 0.5, 'geral' => 0.8],
        ],
        'subcategories' => [
            'acessorios' => [
                'ticket_medio' => ['min' => 100, 'max' => 300, 'media' => 180],
                'taxa_conversao' => ['desktop' => 1.0, 'mobile' => 0.5, 'geral' => 0.8],
                'caracteristicas' => 'Produtos, vibradores, brinquedos',
            ],
            'lingerie' => [
                'ticket_medio' => ['min' => 80, 'max' => 200, 'media' => 130],
                'taxa_conversao' => ['desktop' => 1.2, 'mobile' => 0.6, 'geral' => 0.9],
                'caracteristicas' => 'Fantasias, conjuntos',
            ],
            'cosmeticos' => [
                'ticket_medio' => ['min' => 50, 'max' => 150, 'media' => 90],
                'taxa_conversao' => ['desktop' => 1.5, 'mobile' => 0.8, 'geral' => 1.2],
                'caracteristicas' => 'Lubrificantes, géis, óleos',
            ],
            'geral' => [
                'ticket_medio' => ['min' => 120, 'max' => 280, 'media' => 190],
                'taxa_conversao' => ['desktop' => 1.0, 'mobile' => 0.5, 'geral' => 0.8],
                'caracteristicas' => 'Mix de produtos',
            ],
        ],
    ],

    'livros' => [
        'default' => [
            'ticket_medio' => ['min' => 60, 'max' => 150, 'media' => 100],
            'taxa_conversao' => ['desktop' => 2.0, 'mobile' => 1.2, 'geral' => 1.6],
        ],
        'subcategories' => [
            'livros' => [
                'ticket_medio' => ['min' => 50, 'max' => 120, 'media' => 80],
                'taxa_conversao' => ['desktop' => 2.2, 'mobile' => 1.3, 'geral' => 1.8],
                'caracteristicas' => 'Físicos, recorrência, coleções',
            ],
            'ebooks' => [
                'ticket_medio' => ['min' => 20, 'max' => 60, 'media' => 35],
                'taxa_conversao' => ['desktop' => 3.0, 'mobile' => 2.0, 'geral' => 2.5],
                'caracteristicas' => 'Digital, impulso, bundles',
            ],
            'cursos' => [
                'ticket_medio' => ['min' => 100, 'max' => 500, 'media' => 250],
                'taxa_conversao' => ['desktop' => 1.0, 'mobile' => 0.5, 'geral' => 0.8],
                'caracteristicas' => 'Infoprodutos, alto ticket',
            ],
            'geral' => [
                'ticket_medio' => ['min' => 60, 'max' => 150, 'media' => 100],
                'taxa_conversao' => ['desktop' => 2.0, 'mobile' => 1.2, 'geral' => 1.6],
                'caracteristicas' => 'Mix de livros e mídia',
            ],
        ],
    ],

    'general' => [
        'default' => [
            'ticket_medio' => ['min' => 200, 'max' => 600, 'media' => 350],
            'taxa_conversao' => ['desktop' => 1.5, 'mobile' => 0.8, 'geral' => 1.2],
        ],
        'subcategories' => [
            'geral' => [
                'ticket_medio' => ['min' => 200, 'max' => 600, 'media' => 350],
                'taxa_conversao' => ['desktop' => 1.5, 'mobile' => 0.8, 'geral' => 1.2],
                'caracteristicas' => 'Varejo geral',
            ],
        ],
    ],
];
