<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\AbsenceNotification;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\MeetingRequest;
use App\Models\Student;
use App\Http\Controllers\Staff\PendingTaskController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    /**
     * スタッフダッシュボード統計情報を返す
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroomId = $user->classroom_id;
        $today = Carbon::today();

        // --- 未読チャット数 ---
        $unreadChatCount = ChatRoom::forUser($user)
            ->whereHas('messages', function ($q) use ($user) {
                $q->notDeleted()
                  ->where('sender_type', '!=', 'staff')
                  ->whereDoesntHave('staffReads', function ($r) use ($user) {
                      $r->where('staff_id', $user->id);
                  });
            })
            ->count();

        // --- 本日の出席予定生徒数 ---
        $dayColumn = 'scheduled_' . strtolower($today->format('l'));
        $todayAttendance = Student::where('classroom_id', $classroomId)
            ->active()
            ->where($dayColumn, true)
            ->count();

        // --- 本日の欠席連絡数 ---
        $todayAbsences = AbsenceNotification::whereHas('student', function ($q) use ($classroomId) {
            $q->where('classroom_id', $classroomId);
        })
            ->whereDate('absence_date', $today)
            ->count();

        // --- 振替依頼（未対応）---
        $pendingMakeups = AbsenceNotification::whereHas('student', function ($q) use ($classroomId) {
            $q->where('classroom_id', $classroomId);
        })
            ->where('makeup_status', 'pending')
            ->count();

        // --- 未対応面談リクエスト ---
        $pendingMeetings = MeetingRequest::where('classroom_id', $classroomId)
            ->where('status', 'pending')
            ->count();

        // --- 在籍生徒数 ---
        $activeStudents = Student::where('classroom_id', $classroomId)
            ->active()
            ->count();

        // --- 最近のチャットメッセージ（直近5件）---
        $recentMessages = ChatMessage::notDeleted()
            ->whereHas('room.student', function ($q) use ($classroomId) {
                $q->where('classroom_id', $classroomId);
            })
            ->with(['room.student:id,student_name', 'room.guardian:id,full_name'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'room_id', 'sender_type', 'message', 'created_at']);

        return response()->json([
            'success' => true,
            'data'    => [
                'unread_chat_count'   => $unreadChatCount,
                'today_attendance'    => $todayAttendance,
                'today_absences'      => $todayAbsences,
                'pending_makeups'     => $pendingMakeups,
                'pending_meetings'    => $pendingMeetings,
                'active_students'     => $activeStudents,
                'recent_messages'     => $recentMessages,
            ],
        ]);
    }

    /**
     * ダッシュボード通知サマリー（レガシー renrakucho_activities.php 相当）
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroomId = $user->classroom_id;
        $today = Carbon::today();

        // --- 未読チャット（ルーム別詳細付き） ---
        $unreadRooms = ChatRoom::forUser($user)
            ->whereHas('messages', function ($q) use ($user) {
                $q->notDeleted()
                  ->where('sender_type', '!=', 'staff')
                  ->whereDoesntHave('staffReads', function ($r) use ($user) {
                      $r->where('staff_id', $user->id);
                  });
            })
            ->with(['guardian:id,full_name', 'student:id,student_name'])
            ->get();

        $unreadChatRooms = [];
        $unreadChatTotal = 0;
        foreach ($unreadRooms as $room) {
            $count = $room->messages()
                ->notDeleted()
                ->where('sender_type', '!=', 'staff')
                ->whereDoesntHave('staffReads', function ($r) use ($user) {
                    $r->where('staff_id', $user->id);
                })
                ->count();
            $unreadChatTotal += $count;
            $unreadChatRooms[] = [
                'room_id'       => $room->id,
                'guardian_name' => $room->guardian->full_name ?? null,
                'student_name'  => $room->student->student_name ?? null,
                'count'         => $count,
            ];
        }

        // --- 振替依頼（未対応） ---
        $pendingMakeup = AbsenceNotification::whereHas('student', function ($q) use ($classroomId) {
            $q->where('classroom_id', $classroomId);
        })
            ->where('makeup_status', 'pending')
            ->count();

        // --- 面談リクエスト（保護者カウンター提案） ---
        $pendingMeetingCounter = MeetingRequest::where('classroom_id', $classroomId)
            ->where('status', 'guardian_counter')
            ->count();

        // --- 未確認連絡帳 ---
        $unconfirmedRenrakucho = 0;
        try {
            $unconfirmedRenrakucho = DB::table('integrated_notes')
                ->join('students', 'integrated_notes.student_id', '=', 'students.id')
                ->where('students.classroom_id', $classroomId)
                ->where('integrated_notes.is_sent', true)
                ->where('integrated_notes.guardian_confirmed', false)
                ->count();
        } catch (\Exception $e) {
            Log::warning('integrated_notes table not available: ' . $e->getMessage());
        }

        // --- 保留タスクと同じロジックで件数を取得 ---
        $pendingTaskController = app(PendingTaskController::class);
        $fakeRequest = Request::create('/api/staff/pending-tasks', 'GET');
        $fakeRequest->setUserResolver(fn () => $request->user());
        $pendingResponse = $pendingTaskController->index($fakeRequest);
        $pendingData = json_decode($pendingResponse->getContent(), true);
        $pendingSummary = $pendingData['summary'] ?? [];

        $planOverdue = 0;
        $planUrgent = 0;
        $monitoringOverdue = 0;
        $monitoringUrgent = 0;
        $guardianPending = 0;
        $staffPending = 0;

        // 保留タスクの件数をそのまま使用
        $planCount = $pendingSummary['plans'] ?? 0;
        $monitoringCount = $pendingSummary['monitoring'] ?? 0;
        $guardianPending = $pendingSummary['guardian_kakehashi'] ?? 0;
        $staffPending = $pendingSummary['staff_kakehashi'] ?? 0;

        // plan/monitoringはoverdue/urgentの内訳を保留タスクデータから計算
        $planTasks = $pendingData['data']['plans'] ?? [];
        foreach ($planTasks as $task) {
            $code = $task['status_code'] ?? '';
            if ($code === 'outdated') $planOverdue++;
            else $planUrgent++;
        }
        $monitoringTasks = $pendingData['data']['monitoring'] ?? [];
        foreach ($monitoringTasks as $task) {
            $code = $task['status_code'] ?? '';
            if ($code === 'outdated') $monitoringOverdue++;
            else $monitoringUrgent++;
        }

        // --- 未提出ドキュメント ---
        $unsubmittedDocuments = 0;
        try {
            $unsubmittedDocuments = DB::table('submission_requests')
                ->whereIn('student_id', $students)
                ->where('is_completed', false)
                ->count();
        } catch (\Exception $e) {
            Log::warning('submission_requests table not available: ' . $e->getMessage());
        }

        // --- 施設評価未完了 ---
        $facilityEvaluationIncomplete = false;
        try {
            $facilityEvaluationIncomplete = DB::table('facility_evaluation_periods')
                ->where('classroom_id', $classroomId)
                ->where('status', '!=', 'published')
                ->exists();
        } catch (\Exception $e) {
            Log::warning('facility_evaluations table not available: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'unread_chat' => [
                    'count' => $unreadChatTotal,
                    'rooms' => $unreadChatRooms,
                ],
                'pending_makeup'                => $pendingMakeup,
                'pending_meeting_counter'       => $pendingMeetingCounter,
                'unconfirmed_renrakucho'        => $unconfirmedRenrakucho,
                'plan_deadlines'                => [
                    'overdue' => $planOverdue,
                    'urgent'  => $planUrgent,
                ],
                'monitoring_deadlines'          => [
                    'overdue' => $monitoringOverdue,
                    'urgent'  => $monitoringUrgent,
                ],
                'kakehashi_deadlines'           => [
                    'guardian_pending' => $guardianPending,
                    'staff_pending'   => $staffPending,
                ],
                'unsubmitted_documents'         => $unsubmittedDocuments,
                'facility_evaluation_incomplete' => $facilityEvaluationIncomplete,
            ],
        ]);
    }

    /**
     * ダッシュボードカレンダー情報
     */
    public function calendar(Request $request): JsonResponse
    {
        $classroomId = $request->user()->classroom_id;
        $year = (int) $request->query('year', Carbon::now()->year);
        $month = (int) $request->query('month', Carbon::now()->month);

        $startOfMonth = Carbon::create($year, $month, 1)->startOfDay();
        $endOfMonth = $startOfMonth->copy()->endOfMonth()->endOfDay();

        // --- 活動日（daily_records がある日） ---
        $activityDates = DB::table('daily_records')
            ->where('classroom_id', $classroomId)
            ->whereBetween('record_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->distinct()
            ->pluck('record_date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->values()
            ->toArray();

        // --- 休日 ---
        $holidayDates = DB::table('holidays')
            ->where('classroom_id', $classroomId)
            ->whereBetween('holiday_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->pluck('holiday_date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->values()
            ->toArray();

        // --- イベント ---
        $eventDates = DB::table('events')
            ->where('classroom_id', $classroomId)
            ->whereBetween('event_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->get()
            ->map(fn ($e) => [
                'date'             => Carbon::parse($e->event_date)->toDateString(),
                'label'            => $e->event_name,
                'color'            => $e->event_color ?? null,
                'description'      => $e->event_description ?? null,
                'staff_comment'    => $e->staff_comment ?? null,
                'guardian_message' => $e->guardian_message ?? null,
                'target_audience'  => $e->target_audience ?? null,
            ])
            ->values()
            ->toArray();

        // --- 面談確定日（相手情報付き） ---
        $meetingDates = MeetingRequest::where('classroom_id', $classroomId)
            ->where('status', 'confirmed')
            ->whereNotNull('confirmed_date')
            ->whereBetween('confirmed_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->with(['student:id,student_name', 'guardian:id,full_name'])
            ->get()
            ->map(fn ($m) => [
                'date'          => Carbon::parse($m->confirmed_date)->toDateString(),
                'student_name'  => $m->student->student_name ?? '',
                'guardian_name' => $m->guardian->full_name ?? '',
                'purpose'       => $m->purpose ?? '',
            ])
            ->values()
            ->toArray();

        return response()->json([
            'success' => true,
            'data'    => [
                'activity_dates' => $activityDates,
                'holiday_dates'  => $holidayDates,
                'event_dates'    => $eventDates,
                'meeting_dates'  => $meetingDates,
            ],
        ]);
    }

    /**
     * ダッシュボード出席予定一覧
     */
    public function attendance(Request $request): JsonResponse
    {
        $classroomId = $request->user()->classroom_id;
        $date = Carbon::parse($request->query('date', Carbon::today()->toDateString()));
        $dayColumn = 'scheduled_' . strtolower($date->format('l'));

        // Grade level mapping (grade_level is stored as enum string)
        $gradeGroupMap = function ($gradeLevel): string {
            if ($gradeLevel === null) {
                return '未就学';
            }
            $gl = (string) $gradeLevel;
            if (str_starts_with($gl, 'preschool')) {
                return '未就学';
            }
            if (str_starts_with($gl, 'elementary')) {
                return '小学生';
            }
            if (str_starts_with($gl, 'junior_high')) {
                return '中学生';
            }
            if (str_starts_with($gl, 'high_school')) {
                return '高校生';
            }
            return '未就学';
        };

        $results = [];

        // --- Regular scheduled students ---
        $regularStudents = Student::where('classroom_id', $classroomId)
            ->active()
            ->where($dayColumn, true)
            ->get(['id', 'student_name', 'grade_level']);

        foreach ($regularStudents as $student) {
            $results[$student->id] = [
                'id'          => $student->id,
                'name'        => $student->student_name,
                'grade_group' => $gradeGroupMap($student->grade_level),
                'type'        => 'regular',
                'is_absent'   => false,
            ];
        }

        // --- Makeup students (approved) ---
        try {
            $makeupStudents = AbsenceNotification::whereHas('student', function ($q) use ($classroomId) {
                $q->where('classroom_id', $classroomId)->active();
            })
                ->where('makeup_status', 'approved')
                ->whereDate('makeup_request_date', $date)
                ->with('student:id,student_name,grade_level')
                ->get();

            foreach ($makeupStudents as $makeup) {
                $student = $makeup->student;
                if ($student && !isset($results[$student->id])) {
                    $results[$student->id] = [
                        'id'          => $student->id,
                        'name'        => $student->student_name,
                        'grade_group' => $gradeGroupMap($student->grade_level),
                        'type'        => 'makeup',
                        'is_absent'   => false,
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::warning('Error fetching makeup students: ' . $e->getMessage());
        }

        // --- Additional usage students ---
        try {
            $additionalStudents = DB::table('additional_usages')
                ->join('students', 'additional_usages.student_id', '=', 'students.id')
                ->where('students.classroom_id', $classroomId)
                ->where('students.is_active', true)
                ->whereDate('additional_usages.usage_date', $date)
                ->select('students.id', 'students.student_name', 'students.grade_level')
                ->get();

            foreach ($additionalStudents as $student) {
                if (!isset($results[$student->id])) {
                    $results[$student->id] = [
                        'id'          => $student->id,
                        'name'        => $student->student_name,
                        'grade_group' => $gradeGroupMap($student->grade_level),
                        'type'        => 'additional',
                        'is_absent'   => false,
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::warning('additional_usages table not available: ' . $e->getMessage());
        }

        // --- Mark absent students ---
        if (!empty($results)) {
            $absentStudentIds = AbsenceNotification::whereIn('student_id', array_keys($results))
                ->whereDate('absence_date', $date)
                ->pluck('student_id')
                ->toArray();

            foreach ($absentStudentIds as $studentId) {
                if (isset($results[$studentId])) {
                    $results[$studentId]['is_absent'] = true;
                }
            }
        }

        // --- Activities (daily_records) for this date ---
        $activities = [];
        try {
            $dailyRecords = DB::table('daily_records')
                ->where('daily_records.classroom_id', $classroomId)
                ->whereDate('daily_records.record_date', $date)
                ->leftJoin('users', 'daily_records.staff_id', '=', 'users.id')
                ->select(
                    'daily_records.id',
                    'daily_records.activity_name as name',
                    'daily_records.common_activity',
                    'users.full_name as staff_name'
                )
                ->get();

            foreach ($dailyRecords as $record) {
                $participantCount = 0;
                $sentCount = 0;
                $unsentCount = 0;

                try {
                    $participantCount = DB::table('student_records')
                        ->where('daily_record_id', $record->id)
                        ->count();

                    $sentCount = DB::table('integrated_notes')
                        ->where('daily_record_id', $record->id)
                        ->where('is_sent', true)
                        ->count();

                    $unsentCount = DB::table('integrated_notes')
                        ->where('daily_record_id', $record->id)
                        ->where('is_sent', false)
                        ->count();
                } catch (\Exception $e) {
                    // integrated_notes may not exist
                }

                $activities[] = [
                    'id'                => $record->id,
                    'name'              => $record->name ?? '活動記録',
                    'common_activity'   => $record->common_activity,
                    'staff_name'        => $record->staff_name ?? '不明',
                    'participant_count' => $participantCount,
                    'sent_count'        => $sentCount,
                    'unsent_count'      => $unsentCount,
                ];
            }
        } catch (\Exception $e) {
            Log::warning('Error fetching daily_records: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'students'   => array_values($results),
                'activities' => $activities,
            ],
        ]);
    }
}
