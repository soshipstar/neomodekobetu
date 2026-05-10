<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Student;
use App\Services\BillingService;
use App\Services\PuppeteerPdfService;
use App\Services\WamNetExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BillingController extends Controller
{
    public function __construct(
        private readonly BillingService $service,
        private readonly WamNetExportService $wam,
    ) {}

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
     * 提出前バリデーション (事業所番号など必須項目が揃っているか)。
     */
    public function validateForWamNet(Request $request): JsonResponse
    {
        $request->validate(['year_month' => 'required|string|regex:/^\d{4}-\d{2}$/']);
        $classroomId = $request->user()->classroom_id;
        $errors = $this->wam->validate($classroomId, $request->string('year_month')->toString());
        return response()->json(['data' => ['errors' => $errors, 'ok' => empty($errors)]]);
    }

    /**
     * WAM-NET 形式 (請求書/明細書/実績記録票 の 3 ファイル) を ZIP で返す。
     * Shift-JIS / CRLF。国保連へのオンライン送信ソフトに取り込める形。
     */
    public function downloadWamNetZip(Request $request): Response
    {
        $request->validate(['year_month' => 'required|string|regex:/^\d{4}-\d{2}$/']);
        $classroomId = $request->user()->classroom_id;
        $yearMonth   = $request->string('year_month')->toString();

        $errors = $this->wam->validate($classroomId, $yearMonth);
        if (! empty($errors)) {
            return response()->json([
                'message' => '提出データに不備があります。',
                'errors'  => $errors,
            ], 422);
        }

        $zipPath = $this->wam->generateBundle($classroomId, $yearMonth);
        $filename = "wamnet-{$classroomId}-{$yearMonth}.zip";

        return response()->download($zipPath, $filename, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
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
