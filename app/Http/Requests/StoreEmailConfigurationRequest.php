<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmailConfigurationRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'identifier' => ['nullable', 'string', 'max:255', 'unique:email_configurations,identifier'],
            'provider' => ['required', 'string', Rule::in(['smtp', 'mailgun', 'ses', 'postmark', 'resend'])],
            'is_active' => ['boolean'],
            'settings' => ['required', 'array'],
            'settings.from_address' => ['required', 'email'],
            'settings.from_name' => ['required', 'string', 'max:255'],

            // SMTP specific
            'settings.host' => ['required_if:provider,smtp', 'nullable', 'string'],
            'settings.port' => ['required_if:provider,smtp', 'nullable', 'integer'],
            'settings.username' => ['required_if:provider,smtp', 'nullable', 'string'],
            'settings.password' => ['required_if:provider,smtp', 'nullable', 'string'],
            'settings.encryption' => ['nullable', 'string', Rule::in(['tls', 'ssl'])],

            // Mailgun specific
            'settings.domain' => ['required_if:provider,mailgun', 'nullable', 'string'],
            'settings.api_key' => ['required_if:provider,mailgun,resend', 'nullable', 'string'],
            'settings.api_url' => ['nullable', 'string'],

            // SES specific
            'settings.key' => ['required_if:provider,ses', 'nullable', 'string'],
            'settings.secret' => ['required_if:provider,ses', 'nullable', 'string'],
            'settings.region' => ['required_if:provider,ses', 'nullable', 'string'],

            // Postmark specific
            'settings.token' => ['required_if:provider,postmark', 'nullable', 'string'],
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
