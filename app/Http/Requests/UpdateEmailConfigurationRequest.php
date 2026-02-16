<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmailConfigurationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('admin.access') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'provider' => ['sometimes', 'required', 'string', Rule::in(['smtp', 'mailgun', 'ses', 'postmark', 'resend', 'mailjet'])],
            'is_active' => ['sometimes', 'boolean'],
            'settings' => ['sometimes', 'required', 'array'],
            'settings.from_address' => ['sometimes', 'required', 'email'],
            'settings.from_name' => ['sometimes', 'required', 'string', 'max:255'],

            // SMTP specific
            'settings.host' => ['nullable', 'string'],
            'settings.port' => ['nullable', 'integer'],
            'settings.username' => ['nullable', 'string'],
            'settings.password' => ['nullable', 'string'],
            'settings.encryption' => ['nullable', 'string', Rule::in(['tls', 'ssl'])],

            // Mailgun specific
            'settings.domain' => ['nullable', 'string'],
            'settings.api_key' => ['nullable', 'string'],
            'settings.api_url' => ['nullable', 'string'],

            // SES specific
            'settings.key' => ['nullable', 'string'],
            'settings.secret' => ['nullable', 'string'],
            'settings.region' => ['nullable', 'string'],

            // Postmark specific
            'settings.token' => ['nullable', 'string'],

            // Mailjet specific
            'settings.secret_key' => ['nullable', 'string'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'O nome da configuração é obrigatório.',
            'provider.required' => 'O provedor de e-mail é obrigatório.',
            'provider.in' => 'Provedor inválido.',
            'settings.from_address.required' => 'O endereço de e-mail remetente é obrigatório.',
            'settings.from_address.email' => 'O endereço de e-mail remetente deve ser válido.',
            'settings.from_name.required' => 'O nome do remetente é obrigatório.',
        ];
    }
}
