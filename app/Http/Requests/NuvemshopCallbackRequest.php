<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class NuvemshopCallbackRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only authenticated admins can exchange tokens
        return $this->user()?->hasPermissionTo('admin.access');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:500'],
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
            'code.required' => 'O código de autorização é obrigatório.',
            'code.string' => 'O código de autorização deve ser uma string.',
            'code.max' => 'O código de autorização não pode exceder 500 caracteres.',
        ];
    }
}
