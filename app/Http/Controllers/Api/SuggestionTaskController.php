<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Suggestion;
use App\Models\SuggestionTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SuggestionTaskController extends Controller
{
    use ApiResponse;

    /**
     * List all tasks for a suggestion.
     */
    public function index(Suggestion $suggestion): JsonResponse
    {
        try {
            // Check if user has access to this suggestion's store
            $user = auth()->user();
            if (! $user->hasAccessToStore($suggestion->store_id)) {
                return $this->errorResponse('Você não tem acesso a esta sugestão.', 403);
            }

            $tasks = $suggestion->tasks()
                ->with(['completedBy:id,name', 'createdBy:id,name'])
                ->orderBy('step_index')
                ->orderBy('created_at')
                ->get()
                ->map(function ($task) {
                    return [
                        'id' => $task->id,
                        'step_index' => $task->step_index,
                        'title' => $task->title,
                        'description' => $task->description,
                        'status' => $task->status,
                        'due_date' => $task->due_date?->toDateString(),
                        'completed_at' => $task->completed_at?->toIso8601String(),
                        'completed_by' => $task->completedBy ? [
                            'id' => $task->completedBy->id,
                            'name' => $task->completedBy->name,
                        ] : null,
                        'created_by' => $task->createdBy ? [
                            'id' => $task->createdBy->id,
                            'name' => $task->createdBy->name,
                        ] : null,
                        'created_at' => $task->created_at->toIso8601String(),
                        'is_general' => $task->isGeneral(),
                        'is_linked_to_step' => $task->isLinkedToStep(),
                    ];
                });

            return $this->successResponse(['tasks' => $tasks]);
        } catch (\Exception $e) {
            Log::error('Error fetching suggestion tasks', [
                'suggestion_id' => $suggestion->id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Erro ao buscar tarefas.', 500);
        }
    }

    /**
     * Create a new task for a suggestion.
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
                'step_index' => 'nullable|integer|min:0',
                'title' => 'required|string|max:500',
                'description' => 'nullable|string',
                'status' => 'nullable|string|in:pending,in_progress,completed',
                'due_date' => 'nullable|date|after_or_equal:today',
            ]);

            // If step_index provided, verify it exists in recommended_action
            if (isset($validated['step_index'])) {
                $recommendedActions = $suggestion->recommended_action;
                if (! is_array($recommendedActions) || ! isset($recommendedActions[$validated['step_index']])) {
                    return $this->errorResponse('Índice de passo inválido.', 422);
                }
            }

            $task = $suggestion->tasks()->create([
                'step_index' => $validated['step_index'] ?? null,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'status' => $validated['status'] ?? SuggestionTask::STATUS_PENDING,
                'due_date' => $validated['due_date'] ?? null,
                'created_by' => $user->id,
            ]);

            Log::info('Task created', [
                'task_id' => $task->id,
                'suggestion_id' => $suggestion->id,
                'step_index' => $task->step_index,
                'user_id' => $user->id,
            ]);

            return $this->successResponse([
                'task' => [
                    'id' => $task->id,
                    'step_index' => $task->step_index,
                    'title' => $task->title,
                    'description' => $task->description,
                    'status' => $task->status,
                    'due_date' => $task->due_date?->toDateString(),
                    'completed_at' => $task->completed_at?->toIso8601String(),
                    'completed_by' => null,
                    'created_by' => [
                        'id' => $user->id,
                        'name' => $user->name,
                    ],
                    'created_at' => $task->created_at->toIso8601String(),
                    'is_general' => $task->isGeneral(),
                    'is_linked_to_step' => $task->isLinkedToStep(),
                ],
            ], 'Tarefa criada com sucesso.', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Dados inválidos.', 422, ['errors' => $e->errors()]);
        } catch (\Exception $e) {
            Log::error('Error creating suggestion task', [
                'suggestion_id' => $suggestion->id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Erro ao criar tarefa.', 500);
        }
    }

    /**
     * Update a task.
     */
    public function update(Request $request, Suggestion $suggestion, SuggestionTask $task): JsonResponse
    {
        try {
            // Check if user has access to this suggestion's store
            $user = auth()->user();
            if (! $user->hasAccessToStore($suggestion->store_id)) {
                return $this->errorResponse('Você não tem acesso a esta sugestão.', 403);
            }

            // Check if task belongs to this suggestion
            if ($task->suggestion_id !== $suggestion->id) {
                return $this->errorResponse('Esta tarefa não pertence a esta sugestão.', 404);
            }

            $validated = $request->validate([
                'title' => 'sometimes|string|max:500',
                'description' => 'nullable|string',
                'status' => 'sometimes|string|in:pending,in_progress,completed',
                'due_date' => 'nullable|date',
            ]);

            // Handle status change
            if (isset($validated['status'])) {
                if ($validated['status'] === SuggestionTask::STATUS_COMPLETED && ! $task->isCompleted()) {
                    $task->complete($user);
                    unset($validated['status']); // Already handled by complete()
                } elseif ($validated['status'] === SuggestionTask::STATUS_PENDING && $task->isCompleted()) {
                    $task->uncomplete();
                    unset($validated['status']); // Already handled by uncomplete()
                } elseif ($validated['status'] === SuggestionTask::STATUS_IN_PROGRESS && $task->isPending()) {
                    $task->start();
                    unset($validated['status']); // Already handled by start()
                }
            }

            // Update remaining fields
            if (! empty($validated)) {
                $task->update($validated);
            }

            $task->refresh();
            $task->load(['completedBy:id,name', 'createdBy:id,name']);

            Log::info('Task updated', [
                'task_id' => $task->id,
                'suggestion_id' => $suggestion->id,
                'user_id' => $user->id,
            ]);

            return $this->successResponse([
                'task' => [
                    'id' => $task->id,
                    'step_index' => $task->step_index,
                    'title' => $task->title,
                    'description' => $task->description,
                    'status' => $task->status,
                    'due_date' => $task->due_date?->toDateString(),
                    'completed_at' => $task->completed_at?->toIso8601String(),
                    'completed_by' => $task->completedBy ? [
                        'id' => $task->completedBy->id,
                        'name' => $task->completedBy->name,
                    ] : null,
                    'created_by' => $task->createdBy ? [
                        'id' => $task->createdBy->id,
                        'name' => $task->createdBy->name,
                    ] : null,
                    'created_at' => $task->created_at->toIso8601String(),
                    'is_general' => $task->isGeneral(),
                    'is_linked_to_step' => $task->isLinkedToStep(),
                ],
            ], 'Tarefa atualizada com sucesso.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Dados inválidos.', 422, ['errors' => $e->errors()]);
        } catch (\Exception $e) {
            Log::error('Error updating suggestion task', [
                'task_id' => $task->id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Erro ao atualizar tarefa.', 500);
        }
    }

    /**
     * Delete a task.
     */
    public function destroy(Suggestion $suggestion, SuggestionTask $task): JsonResponse
    {
        try {
            // Check if user has access to this suggestion's store
            $user = auth()->user();
            if (! $user->hasAccessToStore($suggestion->store_id)) {
                return $this->errorResponse('Você não tem acesso a esta sugestão.', 403);
            }

            // Check if task belongs to this suggestion
            if ($task->suggestion_id !== $suggestion->id) {
                return $this->errorResponse('Esta tarefa não pertence a esta sugestão.', 404);
            }

            $task->delete();

            Log::info('Task deleted', [
                'task_id' => $task->id,
                'suggestion_id' => $suggestion->id,
                'user_id' => $user->id,
            ]);

            return $this->successResponse(null, 'Tarefa removida com sucesso.');
        } catch (\Exception $e) {
            Log::error('Error deleting suggestion task', [
                'task_id' => $task->id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Erro ao remover tarefa.', 500);
        }
    }
}
