<?php

namespace App\Http\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

trait ApiResponse
{
    /**
     * Resposta de sucesso padrão
     */
    protected function successResponse(
        mixed $data = null,
        string $message = 'Operacao realizada com sucesso',
        int $statusCode = 200,
        array $meta = []
    ): JsonResponse {
        $response = [
            'success' => true,
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        if (! empty($meta)) {
            $response['meta'] = $meta;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Resposta de erro padrão
     */
    protected function errorResponse(
        string $message = 'Ocorreu um erro',
        int $statusCode = 400,
        array $errors = [],
        ?string $errorCode = null
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errorCode !== null) {
            $response['error_code'] = $errorCode;
        }

        if (! empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Resposta de criação (201 Created)
     */
    protected function createdResponse(
        mixed $data = null,
        string $message = 'Recurso criado com sucesso'
    ): JsonResponse {
        return $this->successResponse($data, $message, 201);
    }

    /**
     * Resposta sem conteúdo (204 No Content)
     */
    protected function noContentResponse(): JsonResponse
    {
        return response()->json(null, 204);
    }

    /**
     * Resposta de não autorizado (401)
     */
    protected function unauthorizedResponse(
        string $message = 'Nao autorizado'
    ): JsonResponse {
        return $this->errorResponse($message, 401, [], 'UNAUTHORIZED');
    }

    /**
     * Resposta de proibido (403)
     */
    protected function forbiddenResponse(
        string $message = 'Acesso negado'
    ): JsonResponse {
        return $this->errorResponse($message, 403, [], 'FORBIDDEN');
    }

    /**
     * Resposta de não encontrado (404)
     */
    protected function notFoundResponse(
        string $message = 'Recurso nao encontrado'
    ): JsonResponse {
        return $this->errorResponse($message, 404, [], 'NOT_FOUND');
    }

    /**
     * Resposta de validação (422)
     */
    protected function validationErrorResponse(
        array $errors,
        string $message = 'Dados invalidos'
    ): JsonResponse {
        return $this->errorResponse($message, 422, $errors, 'VALIDATION_ERROR');
    }

    /**
     * Resposta de erro interno (500)
     */
    protected function serverErrorResponse(
        string $message = 'Erro interno do servidor'
    ): JsonResponse {
        return $this->errorResponse($message, 500, [], 'SERVER_ERROR');
    }

    /**
     * Resposta de rate limit excedido (429)
     */
    protected function rateLimitResponse(
        string $message = 'Muitas requisicoes. Tente novamente em alguns minutos.',
        ?int $retryAfter = null
    ): JsonResponse {
        $response = $this->errorResponse($message, 429, [], 'RATE_LIMIT_EXCEEDED');

        if ($retryAfter !== null) {
            $response->header('Retry-After', $retryAfter);
        }

        return $response;
    }

    /**
     * Resposta paginada
     */
    protected function paginatedResponse(
        LengthAwarePaginator $paginator,
        string $message = 'Lista obtida com sucesso'
    ): JsonResponse {
        return $this->successResponse(
            data: $paginator->items(),
            message: $message,
            meta: [
                'pagination' => [
                    'total' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
                'links' => [
                    'first' => $paginator->url(1),
                    'last' => $paginator->url($paginator->lastPage()),
                    'prev' => $paginator->previousPageUrl(),
                    'next' => $paginator->nextPageUrl(),
                ],
            ]
        );
    }

    /**
     * Resposta com créditos insuficientes
     */
    protected function insufficientCreditsResponse(
        string $message = 'Creditos insuficientes'
    ): JsonResponse {
        return $this->errorResponse($message, 403, [], 'INSUFFICIENT_CREDITS');
    }

    /**
     * Resposta de serviço indisponível (503)
     */
    protected function serviceUnavailableResponse(
        string $message = 'Servico temporariamente indisponivel'
    ): JsonResponse {
        return $this->errorResponse($message, 503, [], 'SERVICE_UNAVAILABLE');
    }
}
