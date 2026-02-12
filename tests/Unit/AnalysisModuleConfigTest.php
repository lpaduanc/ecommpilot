<?php

namespace Tests\Unit;

use App\DTOs\AnalysisModuleConfig;
use PHPUnit\Framework\TestCase;

class AnalysisModuleConfigTest extends TestCase
{
    public function test_general_factory_returns_non_specialized(): void
    {
        $config = AnalysisModuleConfig::general();

        $this->assertFalse($config->isSpecialized);
        $this->assertEmpty($config->collectorFocus);
        $this->assertEmpty($config->analystKeywords);
        $this->assertEmpty($config->strategistConfig);
        $this->assertEmpty($config->criticConfig);
        $this->assertNull($config->temperatureOverride);
    }

    public function test_general_factory_has_correct_type(): void
    {
        $config = AnalysisModuleConfig::general();

        $this->assertEquals('general', $config->analysisType);
    }

    public function test_specialized_config_construction(): void
    {
        $config = new AnalysisModuleConfig(
            analysisType: 'financial',
            isSpecialized: true,
            collectorFocus: ['dados_prioridade' => 'faturamento'],
            analystKeywords: ['keywords' => 'margem'],
            strategistConfig: ['foco' => 'pricing'],
            criticConfig: ['criterios_extras' => 'validar cálculos'],
            temperatureOverride: 0.5,
        );

        $this->assertTrue($config->isSpecialized);
        $this->assertEquals('financial', $config->analysisType);
        $this->assertEquals(0.5, $config->temperatureOverride);
        $this->assertNotEmpty($config->collectorFocus);
        $this->assertNotEmpty($config->analystKeywords);
        $this->assertNotEmpty($config->strategistConfig);
        $this->assertNotEmpty($config->criticConfig);
    }

    public function test_to_array_has_all_keys(): void
    {
        $config = AnalysisModuleConfig::general();
        $array = $config->toArray();

        $expectedKeys = [
            'analysis_type',
            'is_specialized',
            'collector_focus',
            'analyst_keywords',
            'strategist_config',
            'critic_config',
            'temperature_override',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $array);
        }

        $this->assertCount(7, $array);
    }

    public function test_to_array_values_match_properties(): void
    {
        $config = new AnalysisModuleConfig(
            analysisType: 'conversion',
            isSpecialized: true,
            collectorFocus: ['key' => 'value'],
            analystKeywords: ['kw' => 'test'],
            strategistConfig: ['foco' => 'conversão'],
            criticConfig: ['criterios_extras' => 'validar'],
            temperatureOverride: 0.8,
        );

        $array = $config->toArray();

        $this->assertEquals('conversion', $array['analysis_type']);
        $this->assertTrue($array['is_specialized']);
        $this->assertEquals(['key' => 'value'], $array['collector_focus']);
        $this->assertEquals(['kw' => 'test'], $array['analyst_keywords']);
        $this->assertEquals(['foco' => 'conversão'], $array['strategist_config']);
        $this->assertEquals(['criterios_extras' => 'validar'], $array['critic_config']);
        $this->assertEquals(0.8, $array['temperature_override']);
    }
}
