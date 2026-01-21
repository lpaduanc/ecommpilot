<?php

namespace App\Console\Commands;

use App\Models\KnowledgeEmbedding;
use Illuminate\Console\Command;

class FixKnowledgeCategories extends Command
{
    protected $signature = 'knowledge:fix-categories
                            {--dry-run : Preview changes without applying}
                            {--subcategory : Fix subcategories instead of categories}';

    protected $description = 'Fix empty category or subcategory values in knowledge_embeddings table';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $fixSubcategory = $this->option('subcategory');

        if ($dryRun) {
            $this->info('Running in DRY-RUN mode - no changes will be made');
        }

        if ($fixSubcategory) {
            return $this->fixSubcategories($dryRun);
        }

        return $this->fixCategories($dryRun);
    }

    private function fixCategories(bool $dryRun): int
    {
        $records = KnowledgeEmbedding::whereNull('category')
            ->orWhere('category', '')
            ->get();

        if ($records->isEmpty()) {
            $this->info('No records with empty categories found.');

            return self::SUCCESS;
        }

        $this->info("Found {$records->count()} records with empty categories.");
        $this->newLine();

        $fixed = 0;
        $skipped = 0;

        foreach ($records as $record) {
            $category = $this->determineCategory($record);

            if ($category) {
                $this->line("  [{$record->id}] \"{$record->title}\" => <info>{$category}</info>");

                if (! $dryRun) {
                    $record->category = $category;
                    $record->save();
                }

                $fixed++;
            } else {
                $this->warn("  [{$record->id}] Could not determine category for: {$record->title}");
                $skipped++;
            }
        }

        $this->newLine();
        $this->info('Summary:');
        $this->info("  Fixed: {$fixed}");
        $this->info("  Skipped: {$skipped}");

        if ($dryRun && $fixed > 0) {
            $this->newLine();
            $this->comment('Run without --dry-run to apply changes.');
        }

        return self::SUCCESS;
    }

    private function fixSubcategories(bool $dryRun): int
    {
        $records = KnowledgeEmbedding::whereNull('subcategory')
            ->orWhere('subcategory', '')
            ->get();

        if ($records->isEmpty()) {
            $this->info('No records with empty subcategories found.');

            return self::SUCCESS;
        }

        $this->info("Found {$records->count()} records with empty subcategories.");
        $this->newLine();

        $fixed = 0;
        $skipped = 0;

        foreach ($records as $record) {
            $subcategory = $this->determineSubcategory($record);

            if ($subcategory) {
                $this->line("  [{$record->id}] [{$record->niche}] \"{$record->title}\" => <info>{$subcategory}</info>");

                if (! $dryRun) {
                    $record->subcategory = $subcategory;
                    $record->save();
                }

                $fixed++;
            } else {
                $this->line("  [{$record->id}] [{$record->niche}] \"{$record->title}\" => <comment>geral</comment>");

                if (! $dryRun) {
                    $record->subcategory = 'geral';
                    $record->save();
                }

                $fixed++;
            }
        }

        $this->newLine();
        $this->info('Summary:');
        $this->info("  Fixed: {$fixed}");
        $this->info("  Skipped: {$skipped}");

        if ($dryRun && $fixed > 0) {
            $this->newLine();
            $this->comment('Run without --dry-run to apply changes.');
        }

        return self::SUCCESS;
    }

    private function determineCategory(KnowledgeEmbedding $record): ?string
    {
        $title = strtolower($record->title);
        $content = strtolower($record->content ?? '');
        $metadata = $record->metadata ?? [];

        if (str_contains($title, 'benchmark') || str_contains($title, 'dados') || str_contains($title, 'mercado')) {
            return KnowledgeEmbedding::CATEGORY_BENCHMARK;
        }

        if (str_contains($title, 'estratégia') || str_contains($title, 'estrategia') || str_contains($title, 'strategy')) {
            return KnowledgeEmbedding::CATEGORY_STRATEGY;
        }

        if (str_contains($title, 'case') || str_contains($title, 'caso de sucesso') || str_contains($title, 'estudo de caso')) {
            return KnowledgeEmbedding::CATEGORY_CASE;
        }

        if (str_contains($title, 'sazonalidade') || str_contains($title, 'datas') || str_contains($title, 'calendario')) {
            return KnowledgeEmbedding::CATEGORY_SEASONALITY;
        }

        if (isset($metadata['metrics']) || isset($metadata['market_share']) || isset($metadata['average_ticket'])) {
            return KnowledgeEmbedding::CATEGORY_BENCHMARK;
        }

        if (str_contains($content, 'ticket médio') || str_contains($content, 'ticket medio') ||
            str_contains($content, 'taxa de conversão') || str_contains($content, 'taxa de conversao')) {
            return KnowledgeEmbedding::CATEGORY_BENCHMARK;
        }

        if (str_contains($content, 'como implementar') || str_contains($content, 'passo a passo')) {
            return KnowledgeEmbedding::CATEGORY_STRATEGY;
        }

        if (isset($metadata['sources']) && isset($metadata['year'])) {
            return KnowledgeEmbedding::CATEGORY_BENCHMARK;
        }

        return null;
    }

    private function determineSubcategory(KnowledgeEmbedding $record): ?string
    {
        $title = strtolower($record->title);
        $content = strtolower($record->content ?? '');
        $niche = $record->niche;
        $metadata = $record->metadata ?? [];

        // Check metadata for subcategory hint
        if (isset($metadata['subcategory'])) {
            return $metadata['subcategory'];
        }

        // Check avoid_mentions in metadata - this indicates a specific subcategory
        if (isset($metadata['avoid_mentions'])) {
            // The avoid_mentions tells us what NOT to include, which helps identify the subcategory
        }

        // Subcategory mappings by niche
        $subcategoryKeywords = $this->getSubcategoryKeywords();

        if (! isset($subcategoryKeywords[$niche])) {
            return null;
        }

        $bestMatch = null;
        $bestScore = 0;

        foreach ($subcategoryKeywords[$niche] as $subcategory => $keywords) {
            $score = 0;
            foreach ($keywords as $keyword) {
                if (str_contains($title, $keyword)) {
                    $score += 10;
                }
                if (str_contains($content, $keyword)) {
                    $score += 1;
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $subcategory;
            }
        }

        return $bestMatch;
    }

    private function getSubcategoryKeywords(): array
    {
        return [
            'electronics' => [
                'smartphones' => ['smartphone', 'celular', 'iphone', 'samsung', 'xiaomi', 'motorola', 'android'],
                'computers' => ['computador', 'notebook', 'laptop', 'desktop', 'pc', 'informatica', 'informática'],
                'gaming' => ['game', 'gamer', 'gaming', 'console', 'playstation', 'xbox', 'nintendo', 'videogame'],
                'audio' => ['audio', 'áudio', 'fone', 'headset', 'caixa de som', 'speaker', 'soundbar', 'headphone'],
                'tv_video' => ['tv', 'televisão', 'televisao', 'smart tv', 'monitor', 'projetor', 'home theater'],
                'appliances' => ['eletrodoméstico', 'eletrodomestico', 'geladeira', 'fogão', 'microondas', 'lavadora', 'ar condicionado'],
                'wearables' => ['smartwatch', 'wearable', 'relógio inteligente', 'pulseira', 'fitness', 'garmin', 'apple watch'],
            ],
            'fashion' => [
                'women' => ['feminino', 'feminina', 'mulher', 'vestido', 'saia', 'blusa feminina'],
                'men' => ['masculino', 'masculina', 'homem', 'camisa masculina', 'terno'],
                'kids' => ['infantil', 'criança', 'kids', 'bebê', 'menino', 'menina'],
                'shoes' => ['calçado', 'sapato', 'tênis', 'sandália', 'bota', 'chinelo'],
                'bags' => ['bolsa', 'mochila', 'carteira', 'necessaire', 'mala'],
                'jewelry' => ['joia', 'bijuteria', 'acessório', 'colar', 'brinco', 'pulseira', 'anel'],
                'underwear' => ['íntima', 'lingerie', 'calcinha', 'sutiã', 'cueca', 'pijama'],
                'plus_size' => ['plus size', 'plus-size', 'tamanho grande', 'gg', 'xg'],
            ],
            'beauty' => [
                'haircare' => ['cabelo', 'capilar', 'shampoo', 'condicionador', 'hair', 'haircare', 'tratamento capilar'],
                'skincare' => ['pele', 'skincare', 'facial', 'rosto', 'hidratante', 'sérum', 'serum', 'anti-idade'],
                'makeup' => ['maquiagem', 'make', 'makeup', 'batom', 'base', 'sombra', 'rímel'],
                'nails' => ['unha', 'esmalte', 'nail', 'manicure', 'pedicure'],
                'perfumery' => ['perfume', 'perfumaria', 'fragrância', 'fragrance', 'colônia'],
                'barbershop' => ['barba', 'barbearia', 'barbershop', 'masculino', 'homem'],
            ],
            'food' => [
                'gourmet' => ['gourmet', 'premium', 'artesanal', 'importado', 'delicatessen'],
                'healthy' => ['saudável', 'saudavel', 'fit', 'diet', 'light', 'orgânico', 'organico', 'natural', 'vegano'],
                'beverages' => ['bebida', 'café', 'cafe', 'chá', 'cha', 'vinho', 'cerveja', 'suco'],
                'sweets' => ['doce', 'chocolate', 'bombom', 'bolo', 'confeitaria', 'sobremesa'],
                'supplements' => ['suplemento', 'whey', 'proteína', 'proteina', 'vitamina', 'bcaa', 'creatina'],
            ],
            'home' => [
                'furniture' => ['móvel', 'movel', 'sofá', 'sofa', 'mesa', 'cadeira', 'estante', 'cama'],
                'decoration' => ['decoração', 'decoracao', 'quadro', 'vaso', 'espelho', 'tapete', 'cortina'],
                'bedding' => ['cama mesa banho', 'lençol', 'lencol', 'edredom', 'toalha', 'travesseiro'],
                'kitchen' => ['cozinha', 'panela', 'utensílio', 'utensilio', 'eletrodoméstico pequeno'],
                'garden' => ['jardim', 'jardinagem', 'planta', 'horta', 'área externa'],
            ],
            'sports' => [
                'fitness' => ['fitness', 'academia', 'musculação', 'musculacao', 'crossfit', 'yoga', 'pilates'],
                'outdoor' => ['outdoor', 'camping', 'trilha', 'aventura', 'montanhismo'],
                'cycling' => ['ciclismo', 'bicicleta', 'bike', 'pedal', 'mtb'],
                'running' => ['corrida', 'running', 'maratona', 'tênis corrida'],
                'team_sports' => ['futebol', 'vôlei', 'volei', 'basquete', 'time', 'esporte coletivo'],
                'water_sports' => ['natação', 'natacao', 'surf', 'mergulho', 'aquático', 'aquatico', 'piscina'],
            ],
            'pet' => [
                'food' => ['ração', 'racao', 'alimento', 'petisco', 'sachê', 'sache'],
                'accessories' => ['acessório pet', 'coleira', 'guia', 'cama pet', 'casinha', 'brinquedo pet'],
                'hygiene' => ['higiene pet', 'banho pet', 'shampoo pet', 'tosa'],
                'health' => ['saúde pet', 'medicamento pet', 'vermífugo', 'antipulgas'],
            ],
            'kids' => [
                'toys' => ['brinquedo', 'boneca', 'carrinho', 'lego', 'jogo', 'pelúcia'],
                'clothing' => ['roupa infantil', 'moda infantil', 'body', 'macacão'],
                'baby' => ['bebê', 'bebe', 'fralda', 'mamadeira', 'chupeta', 'berço'],
                'education' => ['educativo', 'escolar', 'livro infantil', 'aprendizado'],
            ],
            'health' => [
                'supplements' => ['suplemento', 'vitamina', 'whey', 'proteína', 'bcaa', 'creatina', 'ômega'],
                'natural' => ['natural', 'fitoterápico', 'homeopático', 'ervas', 'óleo essencial'],
                'fitness' => ['fitness', 'academia', 'treino', 'musculação'],
                'pharmacy' => ['farmácia', 'farmacia', 'medicamento', 'remédio', 'remedio'],
            ],
            'general' => [],
        ];
    }
}
