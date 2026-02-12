<?php

namespace Tests\Unit;

use App\Enums\AnalysisType;
use PHPUnit\Framework\TestCase;

class AnalysisTypeEnumTest extends TestCase
{
    public function test_all_six_types_exist(): void
    {
        $cases = AnalysisType::cases();

        $this->assertCount(6, $cases);
        $this->assertNotNull(AnalysisType::General);
        $this->assertNotNull(AnalysisType::Financial);
        $this->assertNotNull(AnalysisType::Conversion);
        $this->assertNotNull(AnalysisType::Competitors);
        $this->assertNotNull(AnalysisType::Campaigns);
        $this->assertNotNull(AnalysisType::Tracking);
    }

    public function test_general_is_default(): void
    {
        $this->assertTrue(AnalysisType::General->isDefault());
        $this->assertFalse(AnalysisType::Financial->isDefault());
        $this->assertFalse(AnalysisType::Conversion->isDefault());
        $this->assertFalse(AnalysisType::Competitors->isDefault());
        $this->assertFalse(AnalysisType::Campaigns->isDefault());
        $this->assertFalse(AnalysisType::Tracking->isDefault());
    }

    public function test_available_types(): void
    {
        $this->assertTrue(AnalysisType::General->available());
        $this->assertTrue(AnalysisType::Financial->available());
        $this->assertTrue(AnalysisType::Conversion->available());
        $this->assertTrue(AnalysisType::Competitors->available());
        $this->assertFalse(AnalysisType::Campaigns->available());
        $this->assertFalse(AnalysisType::Tracking->available());
    }

    public function test_available_types_returns_only_four(): void
    {
        $available = AnalysisType::availableTypes();

        $this->assertCount(4, $available);
    }

    public function test_labels_are_in_portuguese(): void
    {
        foreach (AnalysisType::cases() as $type) {
            $this->assertStringContainsString('Análise', $type->label());
        }
    }

    public function test_values_returns_string_keys(): void
    {
        $values = AnalysisType::values();

        $this->assertCount(6, $values);
        $this->assertContains('general', $values);
        $this->assertContains('financial', $values);
        $this->assertContains('conversion', $values);
        $this->assertContains('competitors', $values);
        $this->assertContains('campaigns', $values);
        $this->assertContains('tracking', $values);
    }

    public function test_to_api_array_structure(): void
    {
        $apiArray = AnalysisType::toApiArray();

        $this->assertCount(6, $apiArray);

        foreach ($apiArray as $item) {
            $this->assertArrayHasKey('key', $item);
            $this->assertArrayHasKey('label', $item);
            $this->assertArrayHasKey('description', $item);
            $this->assertArrayHasKey('available', $item);
            $this->assertArrayHasKey('is_default', $item);
        }
    }

    public function test_to_api_array_has_exactly_one_default(): void
    {
        $apiArray = AnalysisType::toApiArray();

        $defaults = array_filter($apiArray, fn ($item) => $item['is_default'] === true);

        $this->assertCount(1, $defaults);
    }
}
