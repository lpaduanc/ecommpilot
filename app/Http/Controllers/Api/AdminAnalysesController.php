<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminAnalysisDetailResource;
use App\Http\Resources\AdminAnalysisResource;
use App\Models\Analysis;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminAnalysesController extends Controller
{
    /**
     * List analyses with filters and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Analysis::query()
            ->with(['store:id,name', 'user:id,name'])
            ->withCount('persistentSuggestions');

        // Filtros
        if ($request->filled('store_id')) {
            $query->where('store_id', $request->input('store_id'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        // Busca em summary
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(summary::text) LIKE ?', ['%'.strtolower($search).'%'])
                    ->orWhereHas('store', function ($q) use ($search) {
                        $q->where('name', 'like', '%'.$search.'%');
                    })
                    ->orWhereHas('user', function ($q) use ($search) {
                        $q->where('name', 'like', '%'.$search.'%');
                    });
            });
        }

        // Ordenação
        $orderBy = $request->input('order_by', 'created_at');
        $orderDir = $request->input('order_dir', 'desc');
        $query->orderBy($orderBy, $orderDir);

        // Paginação
        $perPage = $request->input('per_page', 15);
        $analyses = $query->paginate($perPage);

        return response()->json([
            'data' => AdminAnalysisResource::collection($analyses),
            'total' => $analyses->total(),
            'last_page' => $analyses->lastPage(),
            'current_page' => $analyses->currentPage(),
        ]);
    }

    /**
     * Show analysis details with execution logs.
     */
    public function show(Analysis $analysis): JsonResponse
    {
        $analysis->load([
            'executionLogs' => fn ($q) => $q->orderBy('stage'),
            'persistentSuggestions',
            'store',
            'user',
        ]);

        return response()->json(new AdminAnalysisDetailResource($analysis));
    }

    /**
     * Get general statistics about analyses.
     */
    public function stats(Request $request): JsonResponse
    {
        // Total por status
        $byStatus = Analysis::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Média de tempo de execução (apenas completed)
        $avgDuration = Analysis::where('status', 'completed')
            ->whereNotNull('completed_at')
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (completed_at - created_at))) as avg_seconds')
            ->value('avg_seconds');

        // Análises por dia (últimos 30 dias)
        $analysesByDay = Analysis::selectRaw('DATE(created_at) as date, count(*) as count')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->mapWithKeys(fn ($item) => [$item->date => $item->count])
            ->toArray();

        // Top stores por análises
        $topStores = Analysis::select('store_id', DB::raw('count(*) as count'))
            ->with('store:id,name')
            ->groupBy('store_id')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(fn ($item) => [
                'store_id' => $item->store_id,
                'store_name' => $item->store->name ?? 'N/A',
                'count' => $item->count,
            ])
            ->toArray();

        return response()->json([
            'by_status' => $byStatus,
            'avg_duration_seconds' => $avgDuration ? round($avgDuration, 2) : null,
            'avg_duration_human' => $avgDuration ? $this->formatDuration($avgDuration) : null,
            'analyses_by_day' => $analysesByDay,
            'top_stores' => $topStores,
        ]);
    }

    /**
     * Format duration in seconds to human-readable format.
     */
    private function formatDuration(float $seconds): string
    {
        if ($seconds < 60) {
            return round($seconds, 1).'s';
        }

        $minutes = floor($seconds / 60);
        $remainingSeconds = round($seconds % 60, 1);

        return "{$minutes}m {$remainingSeconds}s";
    }
}
