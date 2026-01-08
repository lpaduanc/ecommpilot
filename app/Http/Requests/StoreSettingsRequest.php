<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only admins can update store settings
        return $this->user()?->hasPermissionTo('admin.access');
    }

    /**
     * Prepare the data for validation.
     * Convert camelCase keys from frontend to snake_case for backend.
     */
    protected function prepareForValidation(): void
    {
        $input = $this->all();
        $converted = [];

        foreach ($input as $key => $value) {
            $snakeKey = Str::snake($key);
            $converted[$snakeKey] = $value;
        }

        $this->replace($converted);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'client_id' => ['sometimes', 'required', 'string', 'max:255'],
            'client_secret' => ['sometimes', 'required', 'string', 'max:255'],
            'grant_type' => ['sometimes', 'required', 'string', 'in:authorization_code,refresh_token'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'client_id.required' => 'O Client ID é obrigatório.',
            'client_id.string' => 'O Client ID deve ser uma string.',
            'client_id.max' => 'O Client ID não pode exceder 255 caracteres.',
            'client_secret.required' => 'O Client Secret é obrigatório.',
            'client_secret.string' => 'O Client Secret deve ser uma string.',
            'client_secret.max' => 'O Client Secret não pode exceder 255 caracteres.',
            'grant_type.required' => 'O Grant Type é obrigatório.',
            'grant_type.string' => 'O Grant Type deve ser uma string.',
            'grant_type.in' => 'O Grant Type deve ser "authorization_code" ou "refresh_token".',
        ];
    }
}
