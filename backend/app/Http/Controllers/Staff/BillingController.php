<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Services\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BillingController extends Controller
{
    public function __construct(private readonly BillingService $service) {}

    /**
     * 月次請求集計プレビュー (UI のテーブル表示用)。
     */
    public function summary(Request $request): JsonResponse
    {
        $request->validate([
            'year_month' => 'required|string|regex:/^\d{4}-\d{2}$/',
        ]);
        $classroomId = $request->user()->classroom_id;
        $rows = $this->service->buildMonthlyBilling($classroomId, $request->string('year_month'));
        return response()->json([
            'data' => [
                'rows'    => $rows,
                'summary' => $this->service->summarize($rows),
            ],
        ]);
    }

    /**
     * 国保連請求 CSV のダウンロード。
     */
    public function downloadCsv(Request $request): StreamedResponse
    {
        $request->validate([
            'year_month' => 'required|string|regex:/^\d{4}-\d{2}$/',
        ]);
        $classroomId = $request->user()->classroom_id;
        $yearMonth   = $request->string('year_month')->toString();
        $csv = $this->service->generateCsv($classroomId, $yearMonth);

        $filename = "billing-{$classroomId}-{$yearMonth}.csv";
        return response()->streamDownload(
            fn () => print($csv),
            $filename,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]
        );
    }
}
