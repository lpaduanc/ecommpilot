<?php

namespace Tests\Unit;

use App\DTOs\AnalysisModuleConfig;
use App\Services\AI\AnalysisRouter;
use PHPUnit\Framework\TestCase;

class AnalysisRouterTest extends TestCase
{
    private AnalysisRouter $router;

    protected function setUp(): void
    {
        parent::setUp();
        $this->router = new AnalysisRouter;
    }

    // --- Retrocompatibilidade ---

    public function test_general_returns_non_specialized(): void
    {
        $config = $this->router->resolve('general');

        $this->assertInstanceOf(AnalysisModuleConfig::class, $config);
        $this->assertFalse($config->isSpecialized);
        $this->assertEquals('general', $config->analysisType);
    }

    public function test_general_has_empty_configs(): void
    {
        $config = $this->router->resolve('general');

        $this->assertEmpty($config->collectorFocus);
        $this->assertEmpty($config->analystKeywords);
        $this->assertEmpty($config->strategistConfig);
        $this->assertEmpty($config->criticConfig);
        $this->assertNull($config->temperatureOverride);
    }

    // --- Módulo Financial ---

    public function test_financial_returns_specialized(): void
    {
        $config = $this->router->resolve('financial');

        $this->assertTrue($config->isSpecialized);
        $this->assertEquals('financial', $config->analysisType);
    }

    public function test_financial_has_collector_focus(): void
    {
        $config = $this->router->resolve('financial');

        $this->assertArrayHasKey('dados_prioridade', $config->collectorFocus);
        $this->assertArrayHasKey('metricas_obrigatorias', $config->collectorFocus);
        $this->assertIsArray($config->collectorFocus['metricas_obrigatorias']);
    }

    public function test_financial_has_analyst_keywords(): void
    {
        $config = $this->router->resolve('financial');

        $this->assertArrayHasKey('keywords', $config->analystKeywords);
        $this->assertArrayHasKey('foco_analise', $config->analystKeywords);
        $this->assertStringContainsString('margem', $config->analystKeywords['keywords']);
    }

    public function test_financial_has_strategist_config(): void
    {
        $config = $this->router->resolve('financial');

        $this->assertArrayHasKey('foco', $config->strategistConfig);
        $this->assertArrayHasKey('exemplo_bom', $config->strategistConfig);
        $this->assertArrayHasKey('exemplo_ruim', $config->strategistConfig);
    }

    public function test_financial_has_critic_config(): void
    {
        $config = $this->router->resolve('financial');

        $this->assertArrayHasKey('criterios_extras', $config->criticConfig);
        $this->assertNotEmpty($config->criticConfig['criterios_extras']);
    }

    // --- Módulo Conversion ---

    public function test_conversion_returns_specialized(): void
    {
        $config = $this->router->resolve('conversion');

        $this->assertTrue($config->isSpecialized);
        $this->assertEquals('conversion', $config->analysisType);
    }

    public function test_conversion_has_collector_focus(): void
    {
        $config = $this->router->resolve('conversion');

        $this->assertArrayHasKey('dados_prioridade', $config->collectorFocus);
        $this->assertArrayHasKey('metricas_obrigatorias', $config->collectorFocus);
        $this->assertStringContainsString('conversão', $config->collectorFocus['dados_prioridade']);
    }

    // --- Módulo Competitors ---

    public function test_competitors_returns_specialized(): void
    {
        $config = $this->router->resolve('competitors');

        $this->assertTrue($config->isSpecialized);
        $this->assertEquals('competitors', $config->analysisType);
    }

    public function test_competitors_has_collector_focus(): void
    {
        $config = $this->router->resolve('competitors');

        $this->assertArrayHasKey('dados_prioridade', $config->collectorFocus);
        $this->assertArrayHasKey('metricas_obrigatorias', $config->collectorFocus);
        $this->assertCount(5, $config->collectorFocus['metricas_obrigatorias']);
        $this->assertStringContainsString('concorrentes', $config->collectorFocus['dados_prioridade']);
    }

    public function test_competitors_has_analyst_keywords(): void
    {
        $config = $this->router->resolve('competitors');

        $this->assertArrayHasKey('keywords', $config->analystKeywords);
        $this->assertArrayHasKey('foco_analise', $config->analystKeywords);
        $this->assertStringContainsString('posicionamento competitivo', $config->analystKeywords['keywords']);
        $this->assertStringContainsString('(1) PREÇO', $config->analystKeywords['foco_analise']);
    }

    public function test_competitors_has_strategist_config(): void
    {
        $config = $this->router->resolve('competitors');

        $this->assertArrayHasKey('foco', $config->strategistConfig);
        $this->assertArrayHasKey('exemplo_bom', $config->strategistConfig);
        $this->assertArrayHasKey('exemplo_ruim', $config->strategistConfig);
        $this->assertStringContainsString('Beleza Natural', $config->strategistConfig['exemplo_bom']);
    }

    public function test_competitors_has_critic_config(): void
    {
        $config = $this->router->resolve('competitors');

        $this->assertArrayHasKey('criterios_extras', $config->criticConfig);
        $this->assertStringContainsString('PELO NOME', $config->criticConfig['criterios_extras']);
    }

    // --- Módulos não implementados (fallback) ---

    public function test_campaigns_returns_non_specialized(): void
    {
        $config = $this->router->resolve('campaigns');

        $this->assertFalse($config->isSpecialized);
        $this->assertEquals('general', $config->analysisType);
    }

    public function test_tracking_returns_non_specialized(): void
    {
        $config = $this->router->resolve('tracking');

        $this->assertFalse($config->isSpecialized);
        $this->assertEquals('general', $config->analysisType);
    }

    public function test_unknown_type_returns_non_specialized(): void
    {
        $config = $this->router->resolve('tipo_invalido');

        $this->assertFalse($config->isSpecialized);
        $this->assertEquals('general', $config->analysisType);
    }
}
