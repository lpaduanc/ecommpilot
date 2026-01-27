<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmailConfigurationRequest;
use App\Http\Requests\UpdateEmailConfigurationRequest;
use App\Models\EmailConfiguration;
use App\Services\EmailConfigurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminEmailConfigurationController extends Controller
{
    public function __construct(
        private EmailConfigurationService $emailConfigService
    ) {}

    /**
     * Display a listing of email configurations.
     */
    public function index(): JsonResponse
    {
        $configurations = $this->emailConfigService->getAll();

        return response()->json([
            'configs' => $configurations->map(function ($config) {
                return $this->emailConfigService->getForDisplay($config->id);
            }),
            'available_providers' => $this->getAvailableProviders(),
        ]);
    }

    /**
     * Get available email providers.
     */
    private function getAvailableProviders(): array
    {
        return [
            ['id' => 'smtp', 'name' => 'SMTP'],
            ['id' => 'mailgun', 'name' => 'Mailgun'],
            ['id' => 'ses', 'name' => 'Amazon SES'],
            ['id' => 'postmark', 'name' => 'Postmark'],
            ['id' => 'resend', 'name' => 'Resend'],
            ['id' => 'mailjet', 'name' => 'Mailjet'],
        ];
    }

    /**
     * Store a newly created email configuration.
     */
    public function store(StoreEmailConfigurationRequest $request): JsonResponse
    {
        try {
            $configuration = $this->emailConfigService->create($request->validated());

            return response()->json([
                'message' => 'Configuração de e-mail criada com sucesso.',
                'data' => $this->emailConfigService->getForDisplay($configuration->id),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao criar configuração de e-mail.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified email configuration.
     */
    public function show(EmailConfiguration $emailConfiguration): JsonResponse
    {
        try {
            return response()->json([
                'data' => $this->emailConfigService->getForDisplay($emailConfiguration->id),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao buscar configuração.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified email configuration.
     */
    public function update(UpdateEmailConfigurationRequest $request, EmailConfiguration $emailConfiguration): JsonResponse
    {
        try {
            $configuration = $this->emailConfigService->update($emailConfiguration->id, $request->validated());

            return response()->json([
                'message' => 'Configuração de e-mail atualizada com sucesso.',
                'data' => $this->emailConfigService->getForDisplay($configuration->id),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao atualizar configuração de e-mail.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified email configuration.
     */
    public function destroy(EmailConfiguration $emailConfiguration): JsonResponse
    {
        try {
            $this->emailConfigService->delete($emailConfiguration->id);

            return response()->json([
                'message' => 'Configuração de e-mail excluída com sucesso.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao excluir configuração de e-mail.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test email sending with a configuration.
     */
    public function test(Request $request, EmailConfiguration $emailConfiguration): JsonResponse
    {
        $request->validate([
            'test_email' => ['required', 'email'],
        ], [
            'test_email.required' => 'O endereço de e-mail de teste é obrigatório.',
            'test_email.email' => 'O endereço de e-mail de teste deve ser válido.',
        ]);

        try {
            $result = $this->emailConfigService->test($emailConfiguration->id, $request->test_email);

            return response()->json($result, $result['success'] ? 200 : 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao testar configuração de e-mail.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
