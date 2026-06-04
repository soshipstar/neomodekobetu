<?php

namespace App\Http\Controllers\External;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 国保連請求システム（kiduriacount）へ児童・実績データをサーバ間で提供する。
 *
 * 認証は SsoController::verify と同じ共有シークレット方式（auth:sanctum ではない）。
 *   - students:   指定教室の在籍児童＋保護者＋所属教室
 *   - attendance: 指定教室・月の 利用日（連絡帳=integrated_notes の有無）と
 *                 利用時間（attendance_records の到着/帰宅、無ければ null）
 *
 * 本コントローラは追加のみ。既存フローには影響しない。時刻は JST の "H:i" 文字列で返す。
 */
class SyncExportController extends Controller
{
    /** 共有シークレットを検証する。一致しなければ 401 JsonResponse を返す（null=OK）。 */
    private function denyIfBadSecret(Request $request): ?JsonResponse
    {
        $expected = (string) config('services.kiduriacount.sso_secret');
        $given = (string) $request->input('secret');
        if ($expected === '' || ! hash_equals($expected, $given)) {
            return response()->json(['success' => false, 'message' => '認証に失敗しました。'], 401);
        }

        return null;
    }

    /** POST /api/sync/students : 指定教室の児童＋保護者を返す。 */
    public function students(Request $request): JsonResponse
    {
        if ($deny = $this->denyIfBadSecret($request)) {
            return $deny;
        }
        $request->validate([
            'secret' => ['required', 'string'],
            'classroom_ids' => ['required', 'array', 'min:1'],
            'classroom_ids.*' => ['integer'],
        ]);

        $students = Student::query()
            ->with([
                'guardian:id,full_name,full_name_kana,email',
                'classroom:id,classroom_name,company_id',
            ])
            ->whereIn('classroom_id', $request->input('classroom_ids'))
            ->orderBy('classroom_id')
            ->orderBy('student_name_kana')
            ->get();

        $data = $students->map(fn (Student $s) => [
            'k26_student_id' => $s->id,
            'k26_person_id' => $s->person_id,
            'classroom_id' => $s->classroom_id,
            'classroom_name' => $s->classroom?->classroom_name,
            'company_id' => $s->classroom?->company_id,
            'child_name' => $s->student_name,
            'child_name_kana' => $s->student_name_kana,
            'birth_date' => optional($s->birth_date)->format('Y-m-d'),
            'grade_level' => $s->grade_level,
            'status' => $s->status,
            'guardian' => $s->guardian ? [
                'k26_user_id' => $s->guardian->id,
                'full_name' => $s->guardian->full_name,
                'full_name_kana' => $s->guardian->full_name_kana,
                'email' => $s->guardian->email,
            ] : null,
        ])->all();

        return response()->json([
            'success' => true,
            'data' => ['students' => $data],
        ]);
    }

    /** POST /api/sync/attendance : 指定教室・月の利用日（連絡帳）と利用時間（到着帰宅）を返す。 */
    public function attendance(Request $request): JsonResponse
    {
        if ($deny = $this->denyIfBadSecret($request)) {
            return $deny;
        }
        $request->validate([
            'secret' => ['required', 'string'],
            'classroom_id' => ['required', 'integer'],
            'month' => ['required', 'regex:/^\d{6}$/'],
        ]);

        $classroomId = (int) $request->input('classroom_id');
        $month = (string) $request->input('month');
        $start = Carbon::createFromFormat('Ym', $month)->startOfMonth();
        $from = $start->format('Y-m-d');
        $to = $start->copy()->endOfMonth()->format('Y-m-d');

        // 利用日 = 連絡帳（integrated_notes）が存在する日。(student_id, 日付) で一意化。
        $noteRows = DB::table('integrated_notes')
            ->join('daily_records', 'integrated_notes.daily_record_id', '=', 'daily_records.id')
            ->where('daily_records.classroom_id', $classroomId)
            ->whereBetween('daily_records.record_date', [$from, $to])
            ->get(['integrated_notes.student_id', 'daily_records.record_date']);

        // 利用時間 = attendance_records の到着/帰宅（テーブルが無い環境もあるためガード）。
        $timeMap = [];
        if (Schema::hasTable('attendance_records')) {
            $attRows = DB::table('attendance_records')
                ->where('classroom_id', $classroomId)
                ->whereBetween('record_date', [$from, $to])
                ->get(['student_id', 'record_date', 'check_in_time', 'check_out_time']);
            foreach ($attRows as $r) {
                $date = Carbon::parse($r->record_date)->format('Y-m-d');
                $timeMap[$r->student_id.'|'.$date] = [
                    'start_time' => $this->toJstHm($r->check_in_time),
                    'end_time' => $this->toJstHm($r->check_out_time),
                ];
            }
        }

        // (student_id => [date => {start,end}]) に集約（連絡帳の日付を正とする）。
        $byStudent = [];
        foreach ($noteRows as $row) {
            $date = Carbon::parse($row->record_date)->format('Y-m-d');
            $key = $row->student_id.'|'.$date;
            $times = $timeMap[$key] ?? ['start_time' => null, 'end_time' => null];
            $byStudent[$row->student_id][$date] = $times;
        }

        $attendance = [];
        foreach ($byStudent as $studentId => $days) {
            ksort($days);
            $attendance[] = [
                'k26_student_id' => (int) $studentId,
                'days' => array_map(
                    fn ($date, $t) => ['date' => $date, 'start_time' => $t['start_time'], 'end_time' => $t['end_time']],
                    array_keys($days),
                    array_values($days)
                ),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'classroom_id' => $classroomId,
                'month' => $month,
                'attendance' => $attendance,
            ],
        ]);
    }

    /** タイムスタンプを JST の "H:i" に。null/空は null。 */
    private function toJstHm(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value)->setTimezone('Asia/Tokyo')->format('H:i');
    }
}
