<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KnowledgeEmbedding extends Model
{
    use HasFactory;

    protected $fillable = [
        'category',
        'niche',
        'subcategory',
        'title',
        'content',
        'metadata',
        'embedding',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Category constants
     */
    public const CATEGORY_BENCHMARK = 'benchmark';

    public const CATEGORY_STRATEGY = 'strategy';

    public const CATEGORY_CASE = 'case';

    public const CATEGORY_SEASONALITY = 'seasonality';

    /**
     * Niche constants
     */
    public const NICHE_GENERAL = 'general';

    public const NICHE_FASHION = 'fashion';

    public const NICHE_ELECTRONICS = 'electronics';

    public const NICHE_FOOD = 'food';

    public const NICHE_BEAUTY = 'beauty';

    public const NICHE_HOME = 'home';

    public const NICHE_SPORTS = 'sports';

    /**
     * Scope for category.
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope for niche.
     */
    public function scopeByNiche($query, string $niche)
    {
        return $query->where('niche', $niche);
    }

    /**
     * Scope for subcategory.
     */
    public function scopeBySubcategory($query, string $subcategory)
    {
        return $query->where('subcategory', $subcategory);
    }

    /**
     * Scope for niche and subcategory together.
     */
    public function scopeByNicheAndSubcategory($query, string $niche, ?string $subcategory = null)
    {
        $query->where('niche', $niche);

        if ($subcategory) {
            $query->where(function ($q) use ($subcategory) {
                $q->where('subcategory', $subcategory)
                    ->orWhereNull('subcategory');
            });
        }

        return $query;
    }

    /**
     * Scope for benchmarks.
     */
    public function scopeBenchmarks($query)
    {
        return $query->where('category', self::CATEGORY_BENCHMARK);
    }

    /**
     * Scope for strategies.
     */
    public function scopeStrategies($query)
    {
        return $query->where('category', self::CATEGORY_STRATEGY);
    }

    /**
     * Scope for cases.
     */
    public function scopeCases($query)
    {
        return $query->where('category', self::CATEGORY_CASE);
    }

    /**
     * Get all available categories.
     */
    public static function getCategories(): array
    {
        return [
            self::CATEGORY_BENCHMARK,
            self::CATEGORY_STRATEGY,
            self::CATEGORY_CASE,
            self::CATEGORY_SEASONALITY,
        ];
    }

    /**
     * Get all available niches.
     */
    public static function getNiches(): array
    {
        return [
            self::NICHE_GENERAL,
            self::NICHE_FASHION,
            self::NICHE_ELECTRONICS,
            self::NICHE_FOOD,
            self::NICHE_BEAUTY,
            self::NICHE_HOME,
            self::NICHE_SPORTS,
        ];
    }
}
