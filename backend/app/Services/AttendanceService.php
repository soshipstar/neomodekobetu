<?php

namespace App\Services;

use App\Models\AbsenceNotification;
use App\Models\DailyRecord;
use App\Models\Student;
use App\Models\StudentRecord;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * Get today's attendance data for a classroom.
     *
     * Returns an array of students with their attendance status:
     * present, absent (with reason), or not yet recorded.
     *
     * @param  int  $classroomId
     * @return array
     */
    public function getTodayAttendance(int $classroomId): array
    {
        $today = Carbon::today();
        $dayOfWeek = strtolower($today->format('l'));

        // Get all active students in this classroom
        $students = Student::active()
            ->where('classroom_id', $classroomId)
            ->get();

        // Get today's daily record for the classroom
        $dailyRecord = DailyRecord::where('classroom_id', $classroomId)
            ->where('record_date', $today)
            ->first();

        // Get today's absences
        $absences = AbsenceNotification::whereIn('student_id', $students->pluck('id'))
            ->where('absence_date', $today)
            ->get()
            ->keyBy('student_id');

        // Get student records if daily record exists
        $studentRecords = collect();
        if ($dailyRecord) {
            $studentRecords = StudentRecord::where('daily_record_id', $dailyRecord->id)
                ->get()
                ->keyBy('student_id');
        }

        return $students->map(function (Student $student) use ($dayOfWeek, $absences, $studentRecords) {
            $isScheduled = $student->{"scheduled_{$dayOfWeek}"} ?? false;
            $absence = $absences->get($student->id);
            $record = $studentRecords->get($student->id);

            return [
                'student_id' => $student->id,
                'student_name' => $student->student_name,
                'is_scheduled' => $isScheduled,
                'status' => $this->determineAttendanceStatus($isScheduled, $absence, $record),
                'absence_reason' => $absence?->reason,
                'makeup_status' => $absence?->makeup_status,
                'has_record' => $record !== null,
            ];
        })->all();
    }

    /**
     * Get monthly attendance statistics for a classroom.
     *
     * @param  int  $classroomId
     * @param  int  $year
     * @param  int  $month
     * @return array
     */
    public function getMonthlyStats(int $classroomId, int $year, int $month): array
    {
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $students = Student::active()
            ->where('classroom_id', $classroomId)
            ->get();

        // Count daily records (days the classroom was open)
        $operatingDays = DailyRecord::where('classroom_id', $classroomId)
            ->whereBetween('record_date', [$startDate, $endDate])
            ->count();

        // Count student records per student
        $attendanceCounts = DB::table('student_records')
            ->join('daily_records', 'student_records.daily_record_id', '=', 'daily_records.id')
            ->where('daily_records.classroom_id', $classroomId)
            ->whereBetween('daily_records.record_date', [$startDate, $endDate])
            ->select('student_records.student_id', DB::raw('COUNT(*) as attendance_count'))
            ->groupBy('student_records.student_id')
            ->pluck('attendance_count', 'student_id');

        // Count absences per student
        $absenceCounts = AbsenceNotification::whereIn('student_id', $students->pluck('id'))
            ->whereBetween('absence_date', [$startDate, $endDate])
            ->select('student_id', DB::raw('COUNT(*) as absence_count'))
            ->groupBy('student_id')
            ->pluck('absence_count', 'student_id');

        $studentStats = $students->map(function (Student $student) use ($attendanceCounts, $absenceCounts) {
            return [
                'student_id' => $student->id,
                'student_name' => $student->student_name,
                'attendance_count' => $attendanceCounts->get($student->id, 0),
                'absence_count' => $absenceCounts->get($student->id, 0),
            ];
        });

        return [
            'year' => $year,
            'month' => $month,
            'operating_days' => $operatingDays,
            'total_students' => $students->count(),
            'students' => $studentStats->all(),
        ];
    }

    /**
     * Approve a makeup request for an absence notification.
     *
     * @param  AbsenceNotification  $absence
     * @param  int  $staffId
     * @return void
     */
    public function approveMakeup(AbsenceNotification $absence, int $staffId): void
    {
        $absence->update([
            'makeup_status' => 'approved',
            'makeup_approved_by' => $staffId,
        ]);

        // Notify the guardian about the approved makeup
        $guardian = $absence->student->guardian;
        if ($guardian) {
            $this->notificationService->notify(
                $guardian,
                'makeup_approved',
                '振替利用が承認されました',
                "{$absence->student->student_name}さんの振替利用（{$absence->makeup_request_date->format('Y/m/d')}）が承認されました。",
                [
                    'absence_id' => $absence->id,
                    'student_id' => $absence->student_id,
                    'makeup_date' => $absence->makeup_request_date?->toDateString(),
                ]
            );
        }
    }

    /**
     * Determine the attendance status of a student.
     */
    private function determineAttendanceStatus(bool $isScheduled, ?AbsenceNotification $absence, ?StudentRecord $record): string
    {
        if ($absence) {
            return 'absent';
        }

        if ($record) {
            return 'present';
        }

        if ($isScheduled) {
            return 'not_recorded';
        }

        return 'not_scheduled';
    }
}
