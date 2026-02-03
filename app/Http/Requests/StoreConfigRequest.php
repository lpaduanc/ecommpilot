<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $niches = array_keys(config('niches.niches', []));
        $selectedNiche = $this->input('niche');
        $subcategories = [];

        if ($selectedNiche && isset(config('niches.niches')[$selectedNiche])) {
            $subcategories = array_keys(config("niches.niches.{$selectedNiche}.subcategories", []));
        }

        return [
            'niche' => ['nullable', 'string', Rule::in($niches)],
            'niche_subcategory' => [
                'nullable',
                'string',
                'required_with:niche',
                Rule::in($subcategories),
            ],
            'website_url' => ['nullable', 'url', 'max:255'],
            'monthly_goal' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'annual_goal' => ['nullable', 'numeric', 'min:0', 'max:99999999999999.99'],
            'target_ticket' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'monthly_revenue' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'monthly_visits' => ['nullable', 'integer', 'min:0', 'max:2147483647'],
            'competitors' => ['nullable', 'array', 'max:10'],
            'competitors.*.url' => ['required_with:competitors', 'url', 'max:500'],
            'competitors.*.name' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'niche.in' => 'O nicho selecionado não é válido.',
            'niche_subcategory.in' => 'A subcategoria selecionada não é válida para o nicho escolhido.',
            'niche_subcategory.required_with' => 'A subcategoria é obrigatória quando um nicho é selecionado.',
            'website_url.url' => 'A URL do site deve ser uma URL válida.',
            'website_url.max' => 'A URL do site deve ter no máximo 255 caracteres.',
            'monthly_goal.numeric' => 'A meta mensal deve ser um valor numérico.',
            'monthly_goal.min' => 'A meta mensal não pode ser negativa.',
            'annual_goal.numeric' => 'A meta anual deve ser um valor numérico.',
            'annual_goal.min' => 'A meta anual não pode ser negativa.',
            'target_ticket.numeric' => 'O ticket médio alvo deve ser um valor numérico.',
            'target_ticket.min' => 'O ticket médio alvo não pode ser negativo.',
            'monthly_revenue.numeric' => 'O faturamento mensal deve ser um valor numérico.',
            'monthly_revenue.min' => 'O faturamento mensal não pode ser negativo.',
            'monthly_visits.integer' => 'A quantidade de visitas mensais deve ser um número inteiro.',
            'monthly_visits.min' => 'A quantidade de visitas mensais não pode ser negativa.',
            'competitors.array' => 'Os concorrentes devem ser uma lista.',
            'competitors.max' => 'Você pode adicionar no máximo 10 concorrentes.',
            'competitors.*.url.required_with' => 'A URL do concorrente é obrigatória.',
            'competitors.*.url.url' => 'A URL do concorrente deve ser uma URL válida.',
            'competitors.*.name.max' => 'O nome do concorrente deve ter no máximo 100 caracteres.',
        ];
    }

    public function attributes(): array
    {
        return [
            'niche' => 'nicho',
            'niche_subcategory' => 'subcategoria',
            'website_url' => 'URL do site',
            'monthly_goal' => 'meta mensal',
            'annual_goal' => 'meta anual',
            'target_ticket' => 'ticket médio alvo',
            'monthly_revenue' => 'faturamento mensal',
            'monthly_visits' => 'visitas mensais',
            'competitors' => 'concorrentes',
            'competitors.*.url' => 'URL do concorrente',
            'competitors.*.name' => 'nome do concorrente',
        ];
    }
}
