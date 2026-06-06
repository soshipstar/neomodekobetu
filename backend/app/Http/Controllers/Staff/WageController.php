<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\WagePeriod;
use App\Models\WageRecord;
use App\Services\ServiceTypeRegistry;
use App\Services\WageCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 工賃台帳 API。
 *
 * Endpoints:
 *   GET    /api/staff/wage-periods?year_month=YYYY-MM   一覧 + 集計
 *   GET    /api/staff/wage-periods/{period}             詳細 (records 込み)
 *   POST   /api/staff/wage-periods/calculate            計算 (or 再計算)
 *   POST   /api/staff/wage-periods/{period}/finalize    確定
 *   POST   /api/staff/wage-periods/{period}/mark-paid   支払い済みに変更
 *   PUT    /api/staff/wage-records/{record}             個別調整 (賞与・控除等)
 */
class WageController extends Controller
{
    public function __construct(private readonly WageCalculationService $service) {}

    /**
     * 事業所の工賃台帳一覧 (期間別) + 平均工賃。
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroomId = $user->classroom_id;

        // 過去 12 ヶ月分
        $periods = WagePeriod::where('classroom_id', $classroomId)
            ->orderByDesc('year_month')
            ->limit(12)
            ->get()
            ->map(function (WagePeriod $p) {
                $sum = $p->records()->sum('net_wage');
                $avg = $p->records()->where('attendance_days', '>', 0)->avg('net_wage') ?? 0;
                return [
                    'id'              => $p->id,
                    'year_month'      => $p->year_month,
                    'status'          => $p->status,
                    'settlement_date' => optional($p->settlement_date)->toDateString(),
                    'payment_date'    => optional($p->payment_date)->toDateString(),
                    'finalized_at'    => optional($p->finalized_at)->toIso8601String(),
                    'paid_at'         => optional($p->paid_at)->toIso8601String(),
                    'total_wage'      => (float) $sum,
                    'average_wage'    => round((float) $avg, 2),
                    'student_count'   => $p->records()->count(),
                ];
            });

        return response()->json(['data' => $periods]);
    }

    /**
     * 期間の詳細 (利用者ごとの明細) を返す。
     */
    public function show(Request $request, WagePeriod $period): JsonResponse
    {
        $this->authorize($request, $period);

        $records = $period->records()
            ->with('student:id,student_name,wage_calculation_type,hourly_rate,piece_rate_unit,piece_rate_amount,employment_status')
            ->get();

        return response()->json([
            'data' => [
                'period'  => $period,
                'records' => $records,
            ],
        ]);
    }

    /**
     * 計算 (or 再計算) を実行。
     */
    public function calculate(Request $request): JsonResponse
    {
        $request->validate([
            'year_month' => 'required|string|regex:/^\d{4}-\d{2}$/',
        ]);

        $classroomId = $request->user()->classroom_id;
        $result = $this->service->calculate($classroomId, $request->string('year_month'));

        return response()->json([
            'data' => [
                'period'  => $result['period'],
                'records' => array_map(fn (WageRecord $r) => $r->load('student:id,student_name'), $result['records']),
            ],
        ]);
    }

    /**
     * 確定 (以降は再計算されない)。
     */
    public function finalize(Request $request, WagePeriod $period): JsonResponse
    {
        $this->authorize($request, $period);

        if ($period->status === WagePeriod::STATUS_FINALIZED || $period->status === WagePeriod::STATUS_PAID) {
            return response()->json(['message' => '既に確定済みです。'], 422);
        }

        $period->update([
            'status'       => WagePeriod::STATUS_FINALIZED,
            'finalized_at' => now(),
            'finalized_by' => $request->user()->id,
        ]);

        return response()->json(['data' => $period->fresh()]);
    }

    /**
     * 支払い済みに変更。
     */
    public function markPaid(Request $request, WagePeriod $period): JsonResponse
    {
        $this->authorize($request, $period);

        if ($period->status === WagePeriod::STATUS_DRAFT) {
            return response()->json(['message' => '先に確定してください。'], 422);
        }
        if ($period->status === WagePeriod::STATUS_PAID) {
            return response()->json(['message' => '既に支払い済みです。'], 422);
        }

        $request->validate(['payment_date' => 'required|date']);

        $period->update([
            'status'       => WagePeriod::STATUS_PAID,
            'payment_date' => $request->string('payment_date'),
            'paid_at'      => now(),
        ]);

        return response()->json(['data' => $period->fresh()]);
    }

    /**
     * 個別明細の手動調整 (賞与/控除/メモ)。確定済みの場合は拒否。
     */
    public function updateRecord(Request $request, WageRecord $record): JsonResponse
    {
        $period = $record->period;
        $this->authorize($request, $period);
        if ($period->status === WagePeriod::STATUS_PAID) {
            return response()->json(['message' => '支払い済みのため変更できません。'], 422);
        }

        $validated = $request->validate([
            'bonus'         => 'nullable|numeric|min:0|max:9999999',
            'deductions'    => 'nullable|numeric|min:0|max:9999999',
            'overtime_wage' => 'nullable|numeric|min:0|max:9999999',
            'notes'         => 'nullable|string|max:5000',
        ]);

        $record->fill($validated);
        $record->net_wage = (float) $record->base_wage
            + (float) $record->overtime_wage
            + (float) $record->bonus
            - (float) $record->deductions;
        $record->save();

        return response()->json(['data' => $record->fresh()]);
    }

    /**
     * 工賃期間 (WagePeriod) のアクセス制御。
     * LEAK-008 対策: switchableClassroomIds() で照合し、複数教室所属の staff にも対応。
     * (旧コードは $user->classroom_id 単体比較で、pivot 経由の補助教室にアクセスできなかった)
     */
    private function authorize(Request $request, WagePeriod $period): void
    {
        $user = $request->user();
        if (!$user) {
            abort(401);
        }
        if (!in_array((int) $period->classroom_id, $user->switchableClassroomIds(), true)) {
            abort(403, '他事業所の工賃台帳にはアクセスできません。');
        }
    }
}
