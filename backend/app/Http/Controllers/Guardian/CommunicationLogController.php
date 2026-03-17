<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Models\IntegratedNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommunicationLogController extends Controller
{
    /**
     * 連絡帳一覧・検索（統計付き）
     * Legacy: guardian/communication_logs.php
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $studentIds = $user->students()->pluck('students.id');

        $query = IntegratedNote::whereIn('integrated_notes.student_id', $studentIds)
            ->where('integrated_notes.is_sent', true)
            ->join('daily_records', 'integrated_notes.daily_record_id', '=', 'daily_records.id')
            ->join('students', 'integrated_notes.student_id', '=', 'students.id')
            ->leftJoin('student_records', function ($join) {
                $join->on('student_records.daily_record_id', '=', 'daily_records.id')
                     ->on('student_records.student_id', '=', 'students.id');
            });

        // Filter: student_id
        if ($request->filled('student_id')) {
            $query->where('integrated_notes.student_id', $request->student_id);
        }

        // Filter: keyword
        if ($request->filled('keyword')) {
            $keyword = '%' . $request->keyword . '%';
            $query->where(function ($q) use ($keyword) {
                $q->where('integrated_notes.integrated_content', 'ilike', $keyword)
                  ->orWhere('daily_records.activity_name', 'ilike', $keyword)
                  ->orWhere('daily_records.common_activity', 'ilike', $keyword);
            });
        }

        // Filter: start_date
        if ($request->filled('start_date')) {
            $query->where('daily_records.record_date', '>=', $request->start_date);
        }

        // Filter: end_date
        if ($request->filled('end_date')) {
            $query->where('daily_records.record_date', '<=', $request->end_date);
        }

        // Default: last 1 month if no search params
        $isSearching = $request->filled('student_id')
            || $request->filled('keyword')
            || $request->filled('start_date')
            || $request->filled('end_date')
            || $request->filled('domain');

        if (!$isSearching) {
            $query->where('daily_records.record_date', '>=', now()->subMonth()->toDateString());
        }

        // Filter: domain (check if any of the 5 domain columns has content)
        if ($request->filled('domain')) {
            $domain = $request->domain;
            $allowedDomains = [
                'health_life',
                'motor_sensory',
                'cognitive_behavior',
                'language_communication',
                'social_relations',
            ];
            if (in_array($domain, $allowedDomains, true)) {
                $query->whereNotNull("student_records.{$domain}")
                      ->where("student_records.{$domain}", '!=', '');
            }
        }

        $notes = $query
            ->select([
                'integrated_notes.id',
                'integrated_notes.integrated_content',
                'integrated_notes.sent_at',
                'integrated_notes.guardian_confirmed',
                'integrated_notes.guardian_confirmed_at',
                'daily_records.record_date',
                'daily_records.activity_name',
                'daily_records.common_activity',
                'students.id as student_id',
                'students.student_name',
                'student_records.health_life',
                'student_records.motor_sensory',
                'student_records.cognitive_behavior',
                'student_records.language_communication',
                'student_records.social_relations',
            ])
            ->orderByDesc('daily_records.record_date')
            ->orderByDesc('integrated_notes.sent_at')
            ->paginate($request->input('per_page', 50));

        // Build statistics from the current page results
        // For full stats, we compute from all matching results
        $statsQuery = IntegratedNote::whereIn('integrated_notes.student_id', $studentIds)
            ->where('integrated_notes.is_sent', true)
            ->join('daily_records', 'integrated_notes.daily_record_id', '=', 'daily_records.id')
            ->leftJoin('student_records', function ($join) {
                $join->on('student_records.daily_record_id', '=', 'daily_records.id')
                     ->on('student_records.student_id', '=', 'integrated_notes.student_id');
            });

        // Apply same filters for stats
        if ($request->filled('student_id')) {
            $statsQuery->where('integrated_notes.student_id', $request->student_id);
        }
        if ($request->filled('keyword')) {
            $keyword = '%' . $request->keyword . '%';
            $statsQuery->where(function ($q) use ($keyword) {
                $q->where('integrated_notes.integrated_content', 'ilike', $keyword)
                  ->orWhere('daily_records.activity_name', 'ilike', $keyword)
                  ->orWhere('daily_records.common_activity', 'ilike', $keyword);
            });
        }
        if ($request->filled('start_date')) {
            $statsQuery->where('daily_records.record_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $statsQuery->where('daily_records.record_date', '<=', $request->end_date);
        }
        if (!$isSearching) {
            $statsQuery->where('daily_records.record_date', '>=', now()->subMonth()->toDateString());
        }

        $totalCount = $statsQuery->count();

        // Domain counts
        $domainCounts = [];
        foreach (['health_life', 'motor_sensory', 'cognitive_behavior', 'language_communication', 'social_relations'] as $domain) {
            $domainCounts[$domain] = (clone $statsQuery)
                ->whereNotNull("student_records.{$domain}")
                ->where("student_records.{$domain}", '!=', '')
                ->count();
        }

        // Monthly counts
        $monthlyCounts = (clone $statsQuery)
            ->select(DB::raw("to_char(daily_records.record_date, 'YYYY-MM') as month"), DB::raw('count(*) as count'))
            ->groupBy('month')
            ->orderByDesc('month')
            ->pluck('count', 'month');

        return response()->json([
            'success' => true,
            'data'    => $notes,
            'stats'   => [
                'total_count'    => $totalCount,
                'domain_counts'  => $domainCounts,
                'monthly_counts' => $monthlyCounts,
            ],
        ]);
    }
}
