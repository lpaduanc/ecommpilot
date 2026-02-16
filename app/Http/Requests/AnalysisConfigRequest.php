<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnalysisConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'products' => ['nullable', 'array'],
            'products.excluded_product_ids' => ['nullable', 'array'],
            'products.excluded_product_ids.*' => ['integer', 'exists:synced_products,id'],
            'products.exclude_zero_stock' => ['nullable', 'boolean'],
            'products.exclude_gift_products' => ['nullable', 'boolean'],
            'products.exclude_inactive_products' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'products.array' => 'A configuração de produtos deve ser um objeto.',
            'products.excluded_product_ids.array' => 'A lista de produtos excluídos deve ser um array.',
            'products.excluded_product_ids.*.integer' => 'O ID do produto deve ser um número inteiro.',
            'products.excluded_product_ids.*.exists' => 'Um ou mais produtos selecionados não existem.',
            'products.exclude_zero_stock.boolean' => 'A configuração de estoque zero deve ser verdadeiro ou falso.',
            'products.exclude_gift_products.boolean' => 'A configuração de produtos brinde deve ser verdadeiro ou falso.',
            'products.exclude_inactive_products.boolean' => 'A configuração de produtos inativos deve ser verdadeiro ou falso.',
        ];
    }

    public function attributes(): array
    {
        return [
            'products' => 'configuração de produtos',
            'products.excluded_product_ids' => 'produtos excluídos',
            'products.exclude_zero_stock' => 'excluir produtos sem estoque',
            'products.exclude_gift_products' => 'excluir produtos brinde',
            'products.exclude_inactive_products' => 'excluir produtos inativos',
        ];
    }
}
