<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Suggestion;
use App\Models\SuggestionComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SuggestionCommentController extends Controller
{
    use ApiResponse;

    /**
     * List all comments for a suggestion.
     */
    public function index(Suggestion $suggestion): JsonResponse
    {
        try {
            // Check if user has access to this suggestion's store
            $user = auth()->user();
            if (! $user->hasAccessToStore($suggestion->store_id)) {
                return $this->errorResponse('Você não tem acesso a esta sugestão.', 403);
            }

            $comments = $suggestion->comments()
                ->with(['user:id,name,email', 'step:id,title'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($comment) {
                    return [
                        'id' => $comment->id,
                        'content' => $comment->content,
                        'step_id' => $comment->step_id,
                        'step_title' => $comment->step?->title,
                        'user' => $comment->user ? [
                            'id' => $comment->user->id,
                            'name' => $comment->user->name,
                        ] : null,
                        'created_at' => $comment->created_at->toIso8601String(),
                        'is_general' => $comment->isGeneral(),
                    ];
                });

            return $this->successResponse(['comments' => $comments]);
        } catch (\Exception $e) {
            Log::error('Error fetching suggestion comments', [
                'suggestion_id' => $suggestion->id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Erro ao buscar comentários.', 500);
        }
    }

    /**
     * Create a new comment for a suggestion.
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
                'content' => 'required|string|max:2000',
                'step_id' => 'nullable|exists:suggestion_steps,id',
            ]);

            // If step_id provided, verify it belongs to this suggestion
            if (isset($validated['step_id'])) {
                $stepBelongsToSuggestion = $suggestion->steps()
                    ->where('id', $validated['step_id'])
                    ->exists();

                if (! $stepBelongsToSuggestion) {
                    return $this->errorResponse('Este passo não pertence a esta sugestão.', 422);
                }
            }

            $comment = $suggestion->comments()->create([
                'user_id' => $user->id,
                'content' => $validated['content'],
                'step_id' => $validated['step_id'] ?? null,
            ]);

            Log::info('Comment created', [
                'comment_id' => $comment->id,
                'suggestion_id' => $suggestion->id,
                'step_id' => $comment->step_id,
                'user_id' => $user->id,
            ]);

            return $this->successResponse([
                'comment' => [
                    'id' => $comment->id,
                    'content' => $comment->content,
                    'step_id' => $comment->step_id,
                    'step_title' => $comment->step?->title,
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                    ],
                    'created_at' => $comment->created_at->toIso8601String(),
                    'is_general' => $comment->isGeneral(),
                ],
            ], 'Comentário criado com sucesso.', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Dados inválidos.', 422, ['errors' => $e->errors()]);
        } catch (\Exception $e) {
            Log::error('Error creating suggestion comment', [
                'suggestion_id' => $suggestion->id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Erro ao criar comentário.', 500);
        }
    }

    /**
     * Delete a comment (only author or admin).
     */
    public function destroy(Suggestion $suggestion, SuggestionComment $comment): JsonResponse
    {
        try {
            // Check if user has access to this suggestion's store
            $user = auth()->user();
            if (! $user->hasAccessToStore($suggestion->store_id)) {
                return $this->errorResponse('Você não tem acesso a esta sugestão.', 403);
            }

            // Check if comment belongs to this suggestion
            if ($comment->suggestion_id !== $suggestion->id) {
                return $this->errorResponse('Este comentário não pertence a esta sugestão.', 404);
            }

            // Only author or admin can delete
            if ($comment->user_id !== $user->id && ! $user->hasRole('super_admin')) {
                return $this->errorResponse('Você não pode remover este comentário.', 403);
            }

            $comment->delete();

            Log::info('Comment deleted', [
                'comment_id' => $comment->id,
                'suggestion_id' => $suggestion->id,
                'user_id' => $user->id,
            ]);

            return $this->successResponse(null, 'Comentário removido com sucesso.');
        } catch (\Exception $e) {
            Log::error('Error deleting suggestion comment', [
                'comment_id' => $comment->id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Erro ao remover comentário.', 500);
        }
    }
}
