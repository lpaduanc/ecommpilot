<?php

/**
 * Configuração de subcategorias para os prompts de AI.
 *
 * Define produtos permitidos e proibidos por subcategoria para garantir
 * que as sugestões geradas sejam específicas e relevantes.
 */

return [
    'beauty' => [
        'haircare' => [
            'produtos_permitidos' => [
                'shampoo', 'condicionador', 'máscara capilar', 'leave-in',
                'óleo capilar', 'ampola', 'tônico capilar', 'finalizador',
                'creme de pentear', 'spray capilar', 'sérum capilar',
                'tratamento capilar', 'reconstrutor', 'hidratante capilar',
            ],
            'produtos_proibidos' => [
                'hidratante facial', 'sérum facial', 'protetor solar facial',
                'maquiagem', 'batom', 'base', 'esmalte', 'perfume',
                'creme corporal', 'sabonete facial', 'tônico facial',
            ],
            'benchmark_ticket' => ['min' => 120, 'max' => 180],
        ],
        'skincare' => [
            'produtos_permitidos' => [
                'sérum facial', 'hidratante facial', 'protetor solar',
                'limpador facial', 'tônico facial', 'esfoliante facial',
                'máscara facial', 'água micelar', 'creme anti-idade',
                'ácido hialurônico', 'vitamina C', 'retinol', 'niacinamida',
            ],
            'produtos_proibidos' => [
                'shampoo', 'condicionador', 'máscara capilar',
                'maquiagem', 'batom', 'esmalte', 'perfume',
                'creme corporal', 'óleo capilar',
            ],
            'benchmark_ticket' => ['min' => 150, 'max' => 250],
        ],
        'makeup' => [
            'produtos_permitidos' => [
                'base', 'corretivo', 'pó compacto', 'pó solto', 'blush',
                'bronzer', 'iluminador', 'batom', 'gloss', 'rímel',
                'sombra', 'delineador', 'lápis de olho', 'primer',
                'fixador de maquiagem', 'paleta de sombras',
            ],
            'produtos_proibidos' => [
                'shampoo', 'condicionador', 'sérum facial',
                'hidratante corporal', 'óleo capilar', 'protetor solar',
            ],
            'benchmark_ticket' => ['min' => 80, 'max' => 150],
        ],
        'bodycare' => [
            'produtos_permitidos' => [
                'hidratante corporal', 'óleo corporal', 'esfoliante corporal',
                'sabonete líquido', 'creme para mãos', 'creme para pés',
                'desodorante', 'loção corporal', 'manteiga corporal',
            ],
            'produtos_proibidos' => [
                'shampoo', 'maquiagem', 'sérum facial', 'batom',
            ],
            'benchmark_ticket' => ['min' => 80, 'max' => 140],
        ],
    ],

    'sports' => [
        'supplements' => [
            'produtos_permitidos' => [
                'whey protein', 'creatina', 'pré-treino', 'BCAA',
                'glutamina', 'multivitamínico', 'ômega 3', 'cafeína',
                'hipercalórico', 'albumina', 'caseína', 'ZMA',
                'termogênico', 'colágeno', 'vitamina D', 'melatonina',
            ],
            'produtos_proibidos' => [
                'roupa esportiva', 'tênis', 'equipamento', 'acessório fitness',
                'legging', 'top', 'shorts esportivo',
            ],
            'benchmark_ticket' => ['min' => 150, 'max' => 280],
        ],
        'sportswear' => [
            'produtos_permitidos' => [
                'legging', 'top esportivo', 'shorts', 'regata',
                'camiseta esportiva', 'calça esportiva', 'jaqueta',
                'meia esportiva', 'conjunto fitness', 'bermuda',
            ],
            'produtos_proibidos' => [
                'suplemento', 'whey', 'creatina', 'pré-treino',
                'equipamento', 'halteres', 'colchonete',
            ],
            'benchmark_ticket' => ['min' => 120, 'max' => 200],
        ],
        'equipment' => [
            'produtos_permitidos' => [
                'halteres', 'colchonete', 'faixa elástica', 'corda',
                'kettlebell', 'bola de pilates', 'rolo de massagem',
                'luva de treino', 'cinto de musculação', 'joelheira',
            ],
            'produtos_proibidos' => [
                'suplemento', 'whey', 'roupa esportiva', 'legging',
            ],
            'benchmark_ticket' => ['min' => 100, 'max' => 250],
        ],
    ],

    'fashion' => [
        'feminino' => [
            'produtos_permitidos' => [
                'vestido', 'blusa', 'calça', 'saia', 'shorts',
                'jaqueta', 'casaco', 'macacão', 'body', 'cropped',
            ],
            'produtos_proibidos' => [
                'camisa social masculina', 'bermuda masculina', 'terno',
            ],
            'benchmark_ticket' => ['min' => 120, 'max' => 250],
        ],
        'masculino' => [
            'produtos_permitidos' => [
                'camisa', 'camiseta', 'calça', 'bermuda', 'jaqueta',
                'blazer', 'moletom', 'polo', 'shorts', 'casaco',
            ],
            'produtos_proibidos' => [
                'vestido', 'saia', 'body feminino', 'cropped',
            ],
            'benchmark_ticket' => ['min' => 150, 'max' => 300],
        ],
        'acessorios' => [
            'produtos_permitidos' => [
                'bolsa', 'carteira', 'cinto', 'óculos', 'relógio',
                'bijuteria', 'chapéu', 'boné', 'lenço', 'cachecol',
            ],
            'produtos_proibidos' => [
                'vestido', 'camisa', 'calça', 'sapato',
            ],
            'benchmark_ticket' => ['min' => 80, 'max' => 200],
        ],
    ],

    'pet' => [
        'racao' => [
            'produtos_permitidos' => [
                'ração seca', 'ração úmida', 'petisco', 'snack',
                'suplemento pet', 'biscoito para cachorro', 'sachê',
            ],
            'produtos_proibidos' => [
                'coleira', 'brinquedo', 'caminha', 'roupa pet',
            ],
            'benchmark_ticket' => ['min' => 100, 'max' => 200],
        ],
        'acessorios' => [
            'produtos_permitidos' => [
                'coleira', 'guia', 'caminha', 'brinquedo',
                'comedouro', 'bebedouro', 'caixa de transporte',
                'roupa pet', 'tapete higiênico',
            ],
            'produtos_proibidos' => [
                'ração', 'petisco', 'suplemento pet',
            ],
            'benchmark_ticket' => ['min' => 80, 'max' => 180],
        ],
    ],

    'home' => [
        'decoracao' => [
            'produtos_permitidos' => [
                'quadro', 'vaso', 'almofada', 'tapete', 'cortina',
                'luminária', 'espelho', 'porta-retrato', 'escultura',
            ],
            'produtos_proibidos' => [
                'panela', 'prato', 'talher', 'eletrodoméstico',
            ],
            'benchmark_ticket' => ['min' => 100, 'max' => 300],
        ],
        'cozinha' => [
            'produtos_permitidos' => [
                'panela', 'frigideira', 'prato', 'talher', 'copo',
                'jogo de jantar', 'faqueiro', 'forma', 'utensílio',
            ],
            'produtos_proibidos' => [
                'quadro', 'almofada', 'tapete decorativo', 'luminária',
            ],
            'benchmark_ticket' => ['min' => 120, 'max' => 280],
        ],
    ],

    'geral' => [
        'geral' => [
            'produtos_permitidos' => [],
            'produtos_proibidos' => [],
            'benchmark_ticket' => ['min' => 100, 'max' => 200],
        ],
    ],
];
