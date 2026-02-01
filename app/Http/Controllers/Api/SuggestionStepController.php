<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Suggestion;
use App\Models\SuggestionStep;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class SuggestionStepController extends Controller
{
    use ApiResponse;

    /**
     * List all steps for a suggestion.
     */
    public function index(Suggestion $suggestion): JsonResponse
    {
        try {
            // Check if user has access to this suggestion's store
            $user = auth()->user();
            if (! $user->hasAccessToStore($suggestion->store_id)) {
                return $this->errorResponse('Você não tem acesso a esta sugestão.', 403);
            }

            $steps = $suggestion->steps()
                ->with(['completedBy:id,name,email'])
                ->ordered()
                ->get()
                ->map(function ($step) {
                    return [
                        'id' => $step->id,
                        'title' => $step->title,
                        'description' => $step->description,
                        'position' => $step->position,
                        'is_custom' => $step->is_custom,
                        'status' => $step->status,
                        'completed_at' => $step->completed_at?->toIso8601String(),
                        'completed_by' => $step->completedBy ? [
                            'id' => $step->completedBy->id,
                            'name' => $step->completedBy->name,
                        ] : null,
                    ];
                });

            return $this->successResponse([
                'steps' => $steps,
                'progress' => $suggestion->progress,
            ]);
        } catch (\Exception $e) {
            $errorId = 'err_'.uniqid();
            Log::error('Error fetching suggestion steps', [
                'error_id' => $errorId,
                'suggestion_id' => $suggestion->id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->errorResponse('Erro ao buscar passos da sugestão.', 500, ['error_id' => $errorId]);
        }
    }

    /**
     * Create a custom step for a suggestion.
     */
    public function store(Request $request, Suggestion $suggestion): JsonResponse
    {
        try {
            // Check if user has access to this suggestion's store
            $user = auth()->user();
            if (! $user->hasAccessToStore($suggestion->store_id)) {
                return $this->errorResponse('Você não tem acesso a esta sugestão.', 403);
            }

            $validated = $request->validate([
                'title' => 'required|string|max:500',
                'description' => 'nullable|string',
                'position' => 'nullable|integer|min:0',
            ]);

            // If position not provided, add at the end
            if (! isset($validated['position'])) {
                $maxPosition = $suggestion->steps()->max('position') ?? 0;
                $validated['position'] = $maxPosition + 1;
            }

            $step = $suggestion->steps()->create([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'position' => $validated['position'],
                'is_custom' => true,
                'status' => SuggestionStep::STATUS_PENDING,
            ]);

            Log::info('Custom step created', [
                'step_id' => $step->id,
                'suggestion_id' => $suggestion->id,
                'user_id' => $user->id,
            ]);

            return $this->successResponse([
                'step' => [
                    'id' => $step->id,
                    'title' => $step->title,
                    'description' => $step->description,
                    'position' => $step->position,
                    'is_custom' => $step->is_custom,
                    'status' => $step->status,
                ],
                'progress' => $suggestion->fresh()->progress,
            ], 'Passo criado com sucesso.', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Dados inválidos.', 422, ['errors' => $e->errors()]);
        } catch (\Exception $e) {
            $errorId = 'err_'.uniqid();
            Log::error('Error creating suggestion step', [
                'error_id' => $errorId,
                'suggestion_id' => $suggestion->id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->errorResponse('Erro ao criar passo.', 500, ['error_id' => $errorId]);
        }
    }

    /**
     * Update a step (toggle status or edit text).
     */
    public function update(Request $request, Suggestion $suggestion, SuggestionStep $step): JsonResponse
    {
        try {
            // Check if user has access to this suggestion's store
            $user = auth()->user();
            if (! $user->hasAccessToStore($suggestion->store_id)) {
                return $this->errorResponse('Você não tem acesso a esta sugestão.', 403);
            }

            // Check if step belongs to this suggestion
            if ($step->suggestion_id !== $suggestion->id) {
                return $this->errorResponse('Este passo não pertence a esta sugestão.', 404);
            }

            $validated = $request->validate([
                'title' => 'sometimes|string|max:500',
                'description' => 'nullable|string',
                'position' => 'sometimes|integer|min:0',
                'status' => ['sometimes', Rule::in([SuggestionStep::STATUS_PENDING, SuggestionStep::STATUS_COMPLETED])],
            ]);

            // Handle status change
            if (isset($validated['status'])) {
                if ($validated['status'] === SuggestionStep::STATUS_COMPLETED && $step->isPending()) {
                    $step->complete($user);
                } elseif ($validated['status'] === SuggestionStep::STATUS_PENDING && $step->isCompleted()) {
                    $step->uncomplete();
                }

                unset($validated['status']); // Already handled
            }

            // Update other fields
            if (! empty($validated)) {
                $step->update($validated);
            }

            Log::info('Step updated', [
                'step_id' => $step->id,
                'suggestion_id' => $suggestion->id,
                'user_id' => $user->id,
            ]);

            return $this->successResponse([
                'step' => [
                    'id' => $step->id,
                    'title' => $step->title,
                    'description' => $step->description,
                    'position' => $step->position,
                    'is_custom' => $step->is_custom,
                    'status' => $step->status,
                    'completed_at' => $step->completed_at?->toIso8601String(),
                    'completed_by' => $step->completedBy ? [
                        'id' => $step->completedBy->id,
                        'name' => $step->completedBy->name,
                    ] : null,
                ],
                'progress' => $suggestion->fresh()->progress,
            ], 'Passo atualizado com sucesso.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Dados inválidos.', 422, ['errors' => $e->errors()]);
        } catch (\Exception $e) {
            $errorId = 'err_'.uniqid();
            Log::error('Error updating suggestion step', [
                'error_id' => $errorId,
                'step_id' => $step->id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->errorResponse('Erro ao atualizar passo.', 500, ['error_id' => $errorId]);
        }
    }

    /**
     * Delete a step (only custom steps can be deleted).
     */
    public function destroy(Suggestion $suggestion, SuggestionStep $step): JsonResponse
    {
        try {
            // Check if user has access to this suggestion's store
            $user = auth()->user();
            if (! $user->hasAccessToStore($suggestion->store_id)) {
                return $this->errorResponse('Você não tem acesso a esta sugestão.', 403);
            }

            // Check if step belongs to this suggestion
            if ($step->suggestion_id !== $suggestion->id) {
                return $this->errorResponse('Este passo não pertence a esta sugestão.', 404);
            }

            // Only custom steps can be deleted
            if (! $step->is_custom) {
                return $this->errorResponse('Apenas passos customizados podem ser removidos.', 422);
            }

            $step->delete();

            Log::info('Custom step deleted', [
                'step_id' => $step->id,
                'suggestion_id' => $suggestion->id,
                'user_id' => $user->id,
            ]);

            return $this->successResponse([
                'progress' => $suggestion->fresh()->progress,
            ], 'Passo removido com sucesso.');
        } catch (\Exception $e) {
            $errorId = 'err_'.uniqid();
            Log::error('Error deleting suggestion step', [
                'error_id' => $errorId,
                'step_id' => $step->id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->errorResponse('Erro ao remover passo.', 500, ['error_id' => $errorId]);
        }
    }
}
