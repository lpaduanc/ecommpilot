<?php

namespace App\Http\Controllers\Api;

use App\Enums\NotificationType;
use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    /**
     * Listar notificações com paginação e filtros
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Notification::query()
            ->forUser($user->id)
            ->with('store:id,name')
            ->orderBy('created_at', 'desc');

        // Filtro por tipo
        if ($request->has('type') && $request->type !== '') {
            try {
                $type = NotificationType::from($request->type);
                $query->byType($type);
            } catch (\ValueError $e) {
                return response()->json(['message' => 'Tipo de notificação inválido.'], 400);
            }
        }

        // Filtro por status de leitura
        if ($request->has('read') && $request->read !== '') {
            if ($request->read === 'unread') {
                $query->unread();
            } elseif ($request->read === 'read') {
                $query->whereNotNull('read_at');
            }
        }

        // Filtro por data
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->dateRange($request->start_date, $request->end_date);
        }

        $perPage = $request->input('per_page', 20);
        $notifications = $query->paginate($perPage);

        return response()->json([
            'data' => NotificationResource::collection($notifications),
            'total' => $notifications->total(),
            'last_page' => $notifications->lastPage(),
            'current_page' => $notifications->currentPage(),
            'per_page' => $notifications->perPage(),
        ]);
    }

    /**
     * Listar notificações não lidas (para dropdown)
     */
    public function unread(Request $request): JsonResponse
    {
        $user = $request->user();

        $notifications = Notification::query()
            ->forUser($user->id)
            ->unread()
            ->with('store:id,name')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $unreadCount = Notification::query()
            ->forUser($user->id)
            ->unread()
            ->count();

        return response()->json([
            'data' => NotificationResource::collection($notifications),
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * Marcar uma notificação como lida
     */
    public function markAsRead(Request $request, Notification $notification): JsonResponse
    {
        $user = $request->user();

        // Verify notification belongs to user
        if ($notification->user_id !== $user->id) {
            return response()->json(['message' => 'Notificação não encontrada.'], 404);
        }

        $notification->markAsRead();

        Log::info('Notificação marcada como lida', [
            'notification_id' => $notification->id,
            'user_id' => $user->id,
        ]);

        return response()->json([
            'message' => 'Notificação marcada como lida.',
            'data' => new NotificationResource($notification->fresh()),
        ]);
    }

    /**
     * Marcar todas as notificações como lidas
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();

        $updated = Notification::query()
            ->forUser($user->id)
            ->unread()
            ->update(['read_at' => now()]);

        Log::info('Todas as notificações marcadas como lidas', [
            'user_id' => $user->id,
            'count' => $updated,
        ]);

        return response()->json([
            'message' => "{$updated} notificações marcadas como lidas.",
            'count' => $updated,
        ]);
    }

    /**
     * Deletar uma notificação
     */
    public function destroy(Request $request, Notification $notification): JsonResponse
    {
        $user = $request->user();

        // Verify notification belongs to user
        if ($notification->user_id !== $user->id) {
            return response()->json(['message' => 'Notificação não encontrada.'], 404);
        }

        $notification->delete();

        Log::info('Notificação deletada', [
            'notification_id' => $notification->id,
            'user_id' => $user->id,
        ]);

        return response()->json([
            'message' => 'Notificação removida com sucesso.',
        ]);
    }
}
