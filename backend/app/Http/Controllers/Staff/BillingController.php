<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Student;
use App\Services\BillingService;
use App\Services\PuppeteerPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;
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

    /**
     * 利用者ごとのサービス提供実績記録票 PDF。
     */
    public function provisionRecordPdf(Request $request): Response
    {
        $request->validate([
            'year_month' => 'required|string|regex:/^\d{4}-\d{2}$/',
            'student_id' => 'required|integer|exists:students,id',
        ]);
        $classroomId = $request->user()->classroom_id;
        $yearMonth   = $request->string('year_month')->toString();
        $studentId   = (int) $request->integer('student_id');

        $student = Student::findOrFail($studentId);
        if ($student->classroom_id !== $classroomId) {
            abort(403, '他事業所の利用者の記録票は出力できません。');
        }
        $classroom = Classroom::findOrFail($classroomId);

        $rows = $this->service->buildMonthlyBilling($classroomId, $yearMonth);
        $row = collect($rows)->firstWhere('student_id', $studentId);
        if (! $row) {
            abort(404, '対象月の利用記録がありません。');
        }

        $base = Carbon::createFromFormat('Y-m', $yearMonth);
        $daysInMonth = $base->daysInMonth;
        $usageDates = array_flip($row['usage_dates']);
        $dailyMarks = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $date = $base->copy()->day($d)->toDateString();
            $dailyMarks[] = ['day' => $d, 'attended' => isset($usageDates[$date])];
        }

        $data = array_merge($row, [
            'year_month'     => $yearMonth,
            'classroom_name' => $classroom->classroom_name,
            'days_in_month'  => $daysInMonth,
            'daily_marks'    => $dailyMarks,
        ]);

        return PuppeteerPdfService::download(
            'pdf.service-provision-record',
            $data,
            "provision-record-{$studentId}-{$yearMonth}.pdf",
            'A4',
            true,
        );
    }
}
