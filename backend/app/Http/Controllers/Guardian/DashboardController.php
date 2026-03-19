<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Models\ChatRoom;
use App\Models\IndividualSupportPlan;
use App\Models\KakehashiPeriod;
use App\Models\KakehashiStaff;
use App\Models\MeetingRequest;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * 保護者ダッシュボード情報を返す
     * Legacy dashboard.php と同じデータ構造を返す
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $guardianId = $user->id;
        $classroomId = $user->classroom_id;
        $today = Carbon::today()->toDateString();
        $oneWeekLater = Carbon::today()->addDays(7)->toDateString();
        $oneMonthLater = Carbon::today()->addMonth()->toDateString();

        // カレンダー年月
        $year = (int) $request->input('year', date('Y'));
        $month = (int) $request->input('month', date('n'));
        $firstDay = Carbon::createFromDate($year, $month, 1)->startOfMonth()->toDateString();
        $lastDay = Carbon::createFromDate($year, $month, 1)->endOfMonth()->toDateString();

        // 子ども情報 (scheduled_days含む)
        $students = $user->students()
            ->active()
            ->get([
                'id', 'student_name', 'classroom_id', 'grade_level', 'status',
                'scheduled_sunday', 'scheduled_monday', 'scheduled_tuesday',
                'scheduled_wednesday', 'scheduled_thursday', 'scheduled_friday',
                'scheduled_saturday',
            ]);

        $studentIds = $students->pluck('id')->toArray();

        $children = $students->map(function ($s) {
            $scheduledDays = [];
            $dayColumns = [
                0 => 'scheduled_sunday', 1 => 'scheduled_monday',
                2 => 'scheduled_tuesday', 3 => 'scheduled_wednesday',
                4 => 'scheduled_thursday', 5 => 'scheduled_friday',
                6 => 'scheduled_saturday',
            ];
            foreach ($dayColumns as $dayNum => $col) {
                if (!empty($s->$col)) {
                    $scheduledDays[] = $dayNum;
                }
            }
            return [
                'id' => $s->id,
                'student_name' => $s->student_name,
                'grade_level' => $s->grade_level,
                'status' => $s->status,
                'scheduled_days' => $scheduledDays,
            ];
        });

        // ==============================
        // 未読チャットメッセージ (per-room)
        // ==============================
        $unreadChatMessages = DB::select("
            SELECT sub.* FROM (
                SELECT chat_rooms.id as room_id, students.student_name,
                    (SELECT COUNT(*) FROM chat_messages WHERE chat_messages.room_id = chat_rooms.id AND chat_messages.sender_type = 'staff' AND chat_messages.is_read = false AND (chat_messages.is_deleted = false OR chat_messages.is_deleted IS NULL)) as unread_count,
                    (SELECT MAX(chat_messages.created_at) FROM chat_messages WHERE chat_messages.room_id = chat_rooms.id AND chat_messages.sender_type = 'staff' AND chat_messages.is_read = false AND (chat_messages.is_deleted = false OR chat_messages.is_deleted IS NULL)) as last_message_at
                FROM chat_rooms
                INNER JOIN students ON chat_rooms.student_id = students.id
                WHERE chat_rooms.guardian_id = ?
            ) sub WHERE sub.unread_count > 0
            ORDER BY sub.last_message_at DESC
        ", [$guardianId]);

        // ==============================
        // 未提出かけはし (overdue/urgent/pending)
        // ==============================
        $overdueKakehashi = [];
        $urgentKakehashi = [];
        $pendingKakehashi = [];

        if (!empty($studentIds)) {
            $kakehashiRows = DB::select("
                SELECT
                    kp.id as period_id,
                    kp.period_name,
                    kp.submission_deadline,
                    kp.start_date,
                    kp.end_date,
                    kp.student_id,
                    s.student_name,
                    (kp.submission_deadline::date - ?::date) as days_left,
                    kg.id as kakehashi_id,
                    kg.is_submitted,
                    kg.is_hidden
                FROM kakehashi_periods kp
                INNER JOIN students s ON kp.student_id = s.id
                LEFT JOIN kakehashi_guardian kg ON kp.id = kg.period_id AND kg.student_id = kp.student_id
                WHERE kp.student_id = ANY(?)
                AND kp.is_active = true
                AND (kg.is_submitted = false OR kg.is_submitted IS NULL)
                AND (kg.is_hidden = false OR kg.is_hidden IS NULL)
                AND kp.submission_deadline <= ?
                ORDER BY kp.submission_deadline ASC
            ", [$today, '{' . implode(',', $studentIds) . '}', $oneMonthLater]);

            foreach ($kakehashiRows as $row) {
                $item = [
                    'period_id' => $row->period_id,
                    'period_name' => $row->period_name,
                    'submission_deadline' => $row->submission_deadline,
                    'days_left' => (int) $row->days_left,
                    'student_name' => $row->student_name,
                    'student_id' => $row->student_id,
                ];
                if ($row->days_left < 0) {
                    $overdueKakehashi[] = $item;
                } elseif ($row->days_left <= 7) {
                    $urgentKakehashi[] = $item;
                } else {
                    $pendingKakehashi[] = $item;
                }
            }
        }

        // ==============================
        // 未提出の提出物 (overdue/urgent/pending)
        // ==============================
        $overdueSubmissions = [];
        $urgentSubmissions = [];
        $pendingSubmissions = [];

        $submissionRows = DB::table('submission_requests')
            ->join('students', 'submission_requests.student_id', '=', 'students.id')
            ->where('submission_requests.guardian_id', $guardianId)
            ->where('submission_requests.is_completed', false)
            ->selectRaw('submission_requests.id, submission_requests.title, submission_requests.description, submission_requests.due_date, students.student_name')
            ->selectRaw("(submission_requests.due_date::date - ?::date) as days_left", [$today])
            ->orderBy('submission_requests.due_date')
            ->get();

        foreach ($submissionRows as $row) {
            $item = [
                'id' => $row->id,
                'title' => $row->title,
                'description' => $row->description,
                'due_date' => $row->due_date,
                'student_name' => $row->student_name,
                'days_left' => (int) $row->days_left,
            ];
            if ($row->days_left < 0) {
                $overdueSubmissions[] = $item;
            } elseif ($row->days_left <= 3) {
                $urgentSubmissions[] = $item;
            } else {
                $pendingSubmissions[] = $item;
            }
        }

        // ==============================
        // 未確認連絡帳 (per student)
        // ==============================
        $notesData = [];
        if (!empty($studentIds)) {
            try {
                $notes = DB::table('integrated_notes')
                    ->join('daily_records', 'integrated_notes.daily_record_id', '=', 'daily_records.id')
                    ->whereIn('integrated_notes.student_id', $studentIds)
                    ->where('integrated_notes.is_sent', true)
                    ->where('integrated_notes.guardian_confirmed', false)
                    ->select([
                        'integrated_notes.id',
                        'integrated_notes.student_id',
                        'integrated_notes.integrated_content',
                        'integrated_notes.sent_at',
                        'integrated_notes.guardian_confirmed',
                        'integrated_notes.guardian_confirmed_at',
                        'daily_records.activity_name',
                        'daily_records.record_date',
                    ])
                    ->orderByDesc('daily_records.record_date')
                    ->orderByDesc('integrated_notes.sent_at')
                    ->get();

                foreach ($notes as $note) {
                    $sid = $note->student_id;
                    if (!isset($notesData[$sid])) {
                        $notesData[$sid] = [];
                    }
                    if (count($notesData[$sid]) < 10) {
                        $notesData[$sid][] = [
                            'id' => $note->id,
                            'integrated_content' => $note->integrated_content,
                            'sent_at' => $note->sent_at,
                            'guardian_confirmed' => (bool) $note->guardian_confirmed,
                            'guardian_confirmed_at' => $note->guardian_confirmed_at,
                            'activity_name' => $note->activity_name,
                            'record_date' => $note->record_date,
                        ];
                    }
                }
            } catch (\Exception $e) {
                // integrated_notes table may not exist
            }
        }
        // Ensure all students have an entry
        foreach ($studentIds as $sid) {
            if (!isset($notesData[$sid])) {
                $notesData[$sid] = [];
            }
        }

        // ==============================
        // 面談予約
        // ==============================
        $pendingMeetingRequests = MeetingRequest::where('guardian_id', $guardianId)
            ->whereIn('status', ['pending', 'staff_counter'])
            ->where('updated_at', '>=', Carbon::now()->subDays(14))
            ->join('students', 'meeting_requests.student_id', '=', 'students.id')
            ->leftJoin('users', 'meeting_requests.staff_id', '=', 'users.id')
            ->select([
                'meeting_requests.id',
                'students.student_name',
                'users.full_name as staff_name',
                'meeting_requests.status',
                'meeting_requests.purpose',
                'meeting_requests.purpose_detail',
                'meeting_requests.confirmed_date',
            ])
            ->orderByDesc('meeting_requests.created_at')
            ->get();

        $confirmedMeetings = MeetingRequest::where('meeting_requests.guardian_id', $guardianId)
            ->where('meeting_requests.status', 'confirmed')
            ->where('meeting_requests.is_completed', false)
            ->where('meeting_requests.confirmed_date', '>=', $today)
            ->join('students', 'meeting_requests.student_id', '=', 'students.id')
            ->leftJoin('users', 'meeting_requests.staff_id', '=', 'users.id')
            ->select([
                'meeting_requests.id',
                'students.student_name',
                'users.full_name as staff_name',
                'meeting_requests.status',
                'meeting_requests.purpose',
                'meeting_requests.purpose_detail',
                'meeting_requests.confirmed_date',
            ])
            ->orderBy('meeting_requests.confirmed_date')
            ->get();

        // ==============================
        // 未確認の個別支援計画書
        // ==============================
        $pendingSupportPlans = [];
        $signaturePendingPlans = [];

        if (!empty($studentIds)) {
            // 確認待ちの計画書案 (is_draft=false, is_official=false, guardian未レビュー)
            $draftPlans = DB::table('individual_support_plans')
                ->whereIn('student_id', $studentIds)
                ->where('is_draft', false)
                ->where('is_official', false)
                ->where(function ($q) {
                    $q->whereNull('guardian_review_comment')
                      ->orWhere('guardian_review_comment', '');
                })
                ->whereNull('guardian_review_comment_at')
                ->select(['id', 'student_name', 'student_id', 'created_date'])
                ->orderByDesc('created_date')
                ->get();

            foreach ($draftPlans as $plan) {
                $pendingSupportPlans[] = [
                    'id' => $plan->id,
                    'student_name' => $plan->student_name,
                    'student_id' => $plan->student_id,
                    'created_date' => $plan->created_date,
                ];
            }

            // 署名待ちの正式版 (is_official=true, guardian_confirmed=false)
            $officialPlans = DB::table('individual_support_plans')
                ->whereIn('student_id', $studentIds)
                ->where('is_official', true)
                ->where('guardian_confirmed', false)
                ->select(['id', 'student_name', 'student_id', 'created_date'])
                ->orderByDesc('created_date')
                ->get();

            foreach ($officialPlans as $plan) {
                $signaturePendingPlans[] = [
                    'id' => $plan->id,
                    'student_name' => $plan->student_name,
                    'student_id' => $plan->student_id,
                    'created_date' => $plan->created_date,
                ];
            }
        }

        // ==============================
        // 未確認モニタリング
        // ==============================
        $pendingMonitoringRecords = [];
        $signaturePendingMonitoring = [];

        if (!empty($studentIds)) {
            $monitoringDraft = DB::table('monitoring_records')
                ->join('students', 'monitoring_records.student_id', '=', 'students.id')
                ->whereIn('monitoring_records.student_id', $studentIds)
                ->where('monitoring_records.is_draft', false)
                ->where(function ($q) {
                    $q->where('monitoring_records.is_official', false)
                      ->orWhereNull('monitoring_records.is_official');
                })
                ->where(function ($q) {
                    $q->where('monitoring_records.guardian_confirmed', false)
                      ->orWhereNull('monitoring_records.guardian_confirmed');
                })
                ->select(['monitoring_records.id', 'monitoring_records.student_id', 'monitoring_records.monitoring_date', 'students.student_name'])
                ->orderByDesc('monitoring_records.monitoring_date')
                ->get();

            foreach ($monitoringDraft as $record) {
                $pendingMonitoringRecords[] = [
                    'id' => $record->id,
                    'student_name' => $record->student_name,
                    'student_id' => $record->student_id,
                    'monitoring_date' => $record->monitoring_date,
                ];
            }

            $monitoringOfficial = DB::table('monitoring_records')
                ->join('students', 'monitoring_records.student_id', '=', 'students.id')
                ->whereIn('monitoring_records.student_id', $studentIds)
                ->where('monitoring_records.is_official', true)
                ->where(function ($q) {
                    $q->where('monitoring_records.guardian_confirmed', false)
                      ->orWhereNull('monitoring_records.guardian_confirmed');
                })
                ->select(['monitoring_records.id', 'monitoring_records.student_id', 'monitoring_records.monitoring_date', 'students.student_name'])
                ->orderByDesc('monitoring_records.monitoring_date')
                ->get();

            foreach ($monitoringOfficial as $record) {
                $signaturePendingMonitoring[] = [
                    'id' => $record->id,
                    'student_name' => $record->student_name,
                    'student_id' => $record->student_id,
                    'monitoring_date' => $record->monitoring_date,
                ];
            }
        }

        // ==============================
        // スタッフかけはしの確認待ち
        // ==============================
        $pendingStaffKakehashi = [];
        if (!empty($studentIds)) {
            $staffKakehashi = DB::table('kakehashi_staff')
                ->join('kakehashi_periods', 'kakehashi_staff.period_id', '=', 'kakehashi_periods.id')
                ->join('students', 'kakehashi_staff.student_id', '=', 'students.id')
                ->whereIn('kakehashi_staff.student_id', $studentIds)
                ->where('kakehashi_staff.is_submitted', true)
                ->where(function ($q) {
                    $q->where('kakehashi_staff.guardian_confirmed', false)
                      ->orWhereNull('kakehashi_staff.guardian_confirmed');
                })
                ->select([
                    'kakehashi_staff.id',
                    'kakehashi_staff.submitted_at',
                    'kakehashi_periods.period_name',
                    'students.student_name',
                    'students.id as student_id',
                ])
                ->orderByDesc('kakehashi_staff.submitted_at')
                ->get();

            foreach ($staffKakehashi as $sk) {
                $pendingStaffKakehashi[] = [
                    'id' => $sk->id,
                    'student_name' => $sk->student_name,
                    'student_id' => $sk->student_id,
                    'period_name' => $sk->period_name,
                    'submitted_at' => $sk->submitted_at,
                ];
            }
        }

        // ==============================
        // 事業所評価アンケート
        // ==============================
        $pendingFacilityEvaluations = [];
        if ($classroomId) {
            $evals = DB::table('facility_evaluation_periods')
                ->leftJoin('facility_guardian_evaluations', function ($join) use ($guardianId) {
                    $join->on('facility_evaluation_periods.id', '=', 'facility_guardian_evaluations.period_id')
                         ->where('facility_guardian_evaluations.guardian_id', '=', $guardianId);
                })
                ->where('facility_evaluation_periods.status', 'collecting')
                ->where('facility_evaluation_periods.classroom_id', $classroomId)
                ->where(function ($q) {
                    $q->where('facility_guardian_evaluations.is_submitted', false)
                      ->orWhereNull('facility_guardian_evaluations.is_submitted');
                })
                ->select([
                    'facility_evaluation_periods.id',
                    'facility_evaluation_periods.title',
                    'facility_evaluation_periods.guardian_deadline',
                ])
                ->orderByDesc('facility_evaluation_periods.fiscal_year')
                ->get();

            foreach ($evals as $ev) {
                $pendingFacilityEvaluations[] = [
                    'id' => $ev->id,
                    'title' => $ev->title,
                    'guardian_deadline' => $ev->guardian_deadline,
                ];
            }
        }

        // ==============================
        // カレンダーデータ
        // ==============================
        $calendar = $this->buildCalendarData(
            $guardianId, $classroomId, $studentIds, $students,
            $year, $month, $firstDay, $lastDay
        );

        return response()->json([
            'success' => true,
            'data'    => [
                'children'                    => $children,
                'unread_chat_messages'        => $unreadChatMessages,
                'overdue_kakehashi'           => $overdueKakehashi,
                'urgent_kakehashi'            => $urgentKakehashi,
                'pending_kakehashi'           => $pendingKakehashi,
                'overdue_submissions'         => $overdueSubmissions,
                'urgent_submissions'          => $urgentSubmissions,
                'pending_submissions'         => $pendingSubmissions,
                'notes_data'                  => $notesData,
                'pending_meeting_requests'    => $pendingMeetingRequests,
                'confirmed_meetings'          => $confirmedMeetings,
                'pending_support_plans'       => $pendingSupportPlans,
                'signature_pending_plans'     => $signaturePendingPlans,
                'pending_monitoring_records'  => $pendingMonitoringRecords,
                'signature_pending_monitoring' => $signaturePendingMonitoring,
                'pending_staff_kakehashi'     => $pendingStaffKakehashi,
                'pending_facility_evaluations' => $pendingFacilityEvaluations,
                'calendar'                    => $calendar,
            ],
        ]);
    }

    /**
     * カレンダーデータを構築 (legacy dashboard.php と同等)
     */
    private function buildCalendarData(
        int $guardianId,
        ?int $classroomId,
        array $studentIds,
        $students,
        int $year,
        int $month,
        string $firstDay,
        string $lastDay
    ): array {
        // 休日
        $holidays = [];
        $holidayQuery = DB::table('holidays')
            ->whereRaw('EXTRACT(YEAR FROM holiday_date) = ?', [$year])
            ->whereRaw('EXTRACT(MONTH FROM holiday_date) = ?', [$month]);
        if ($classroomId) {
            $holidayQuery->where('classroom_id', $classroomId);
        }
        foreach ($holidayQuery->get() as $h) {
            $holidays[$h->holiday_date] = [
                'name' => $h->holiday_name,
                'type' => $h->holiday_type,
            ];
        }

        // イベント
        $events = [];
        $eventQuery = DB::table('events')
            ->whereRaw('EXTRACT(YEAR FROM event_date) = ?', [$year])
            ->whereRaw('EXTRACT(MONTH FROM event_date) = ?', [$month]);
        if ($classroomId) {
            $eventQuery->where('classroom_id', $classroomId);
        }
        foreach ($eventQuery->get() as $ev) {
            $events[$ev->event_date][] = [
                'id' => $ev->id,
                'name' => $ev->event_name,
                'description' => $ev->event_description,
                'guardian_message' => $ev->guardian_message ?? '',
                'target_audience' => $ev->target_audience ?? '',
                'color' => $ev->event_color ?? '#22c55e',
            ];
        }

        // 学校休業日活動
        $schoolHolidayActivities = [];
        if ($classroomId) {
            try {
                $shaRows = DB::table('school_holiday_activities')
                    ->where('classroom_id', $classroomId)
                    ->whereRaw('EXTRACT(YEAR FROM activity_date) = ?', [$year])
                    ->whereRaw('EXTRACT(MONTH FROM activity_date) = ?', [$month])
                    ->pluck('activity_date');
                foreach ($shaRows as $date) {
                    $schoolHolidayActivities[$date] = true;
                }
            } catch (\Exception $e) {
                // table may not exist
            }
        }

        // 生徒の予定日
        $dayColumns = [
            0 => 'scheduled_sunday', 1 => 'scheduled_monday',
            2 => 'scheduled_tuesday', 3 => 'scheduled_wednesday',
            4 => 'scheduled_thursday', 5 => 'scheduled_friday',
            6 => 'scheduled_saturday',
        ];

        $studentScheduleMap = [];
        foreach ($students as $s) {
            $scheduledDays = [];
            foreach ($dayColumns as $dayNum => $col) {
                if (!empty($s->$col)) {
                    $scheduledDays[] = $dayNum;
                }
            }
            $studentScheduleMap[$s->id] = [
                'name' => $s->student_name,
                'scheduled_days' => $scheduledDays,
            ];
        }

        // カレンダースケジュール
        $schedules = [];
        $daysInMonth = Carbon::createFromDate($year, $month, 1)->daysInMonth;
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $dow = (int) Carbon::parse($dateStr)->dayOfWeek;
            $isHoliday = isset($holidays[$dateStr]);

            $schedules[$dateStr] = [];
            foreach ($studentScheduleMap as $sid => $sched) {
                if (!$isHoliday && in_array($dow, $sched['scheduled_days'])) {
                    $schedules[$dateStr][] = [
                        'student_id' => $sid,
                        'student_name' => $sched['name'],
                    ];
                }
            }
        }

        // 連絡帳
        $notes = [];
        if (!empty($studentIds)) {
            try {
                $noteRows = DB::table('integrated_notes')
                    ->join('daily_records', 'integrated_notes.daily_record_id', '=', 'daily_records.id')
                    ->join('students', 'integrated_notes.student_id', '=', 'students.id')
                    ->whereIn('integrated_notes.student_id', $studentIds)
                    ->where('integrated_notes.is_sent', true)
                    ->whereBetween('daily_records.record_date', [$firstDay, $lastDay])
                    ->select([
                        'integrated_notes.student_id',
                        'integrated_notes.guardian_confirmed',
                        'daily_records.record_date',
                        'students.student_name',
                    ])
                    ->get();

                foreach ($noteRows as $n) {
                    $notes[$n->record_date][] = [
                        'student_id' => $n->student_id,
                        'student_name' => $n->student_name,
                        'guardian_confirmed' => (bool) $n->guardian_confirmed,
                    ];
                }
            } catch (\Exception $e) {
                // integrated_notes table may not exist
            }
        }

        // 振替活動日・欠席日
        $makeupDays = [];
        $absenceDays = [];
        if (!empty($studentIds)) {
            try {
                // 振替日 (approved)
                $mkRows = DB::table('absence_notifications')
                    ->join('students', 'absence_notifications.student_id', '=', 'students.id')
                    ->whereIn('absence_notifications.student_id', $studentIds)
                    ->where('absence_notifications.makeup_status', 'approved')
                    ->whereBetween('absence_notifications.makeup_request_date', [$firstDay, $lastDay])
                    ->select(['absence_notifications.student_id', 'absence_notifications.makeup_request_date', 'students.student_name'])
                    ->get();

                foreach ($mkRows as $mk) {
                    $makeupDays[$mk->makeup_request_date][] = [
                        'student_id' => $mk->student_id,
                        'student_name' => $mk->student_name,
                    ];
                }

                // 欠席日
                $absRows = DB::table('absence_notifications')
                    ->join('students', 'absence_notifications.student_id', '=', 'students.id')
                    ->whereIn('absence_notifications.student_id', $studentIds)
                    ->whereBetween('absence_notifications.absence_date', [$firstDay, $lastDay])
                    ->select(['absence_notifications.student_id', 'absence_notifications.absence_date', 'absence_notifications.reason', 'students.student_name'])
                    ->get();

                foreach ($absRows as $abs) {
                    $absenceDays[$abs->absence_date][] = [
                        'student_id' => $abs->student_id,
                        'student_name' => $abs->student_name,
                        'reason' => $abs->reason,
                    ];
                }
            } catch (\Exception $e) {
                // table may not exist
            }
        }

        // 追加利用日
        $additionalDays = [];
        if (!empty($studentIds)) {
            try {
                $addRows = DB::table('additional_usages')
                    ->join('students', 'additional_usages.student_id', '=', 'students.id')
                    ->whereIn('additional_usages.student_id', $studentIds)
                    ->whereBetween('additional_usages.usage_date', [$firstDay, $lastDay])
                    ->select(['additional_usages.student_id', 'additional_usages.usage_date', 'students.student_name'])
                    ->get();

                foreach ($addRows as $ad) {
                    $additionalDays[$ad->usage_date][] = [
                        'student_id' => $ad->student_id,
                        'student_name' => $ad->student_name,
                    ];
                }
            } catch (\Exception $e) {
                // table may not exist
            }
        }

        // 確定済み面談 (calendar)
        $meetings = [];
        $meetingRows = DB::table('meeting_requests')
            ->join('students', 'meeting_requests.student_id', '=', 'students.id')
            ->leftJoin('users', 'meeting_requests.staff_id', '=', 'users.id')
            ->where('meeting_requests.guardian_id', $guardianId)
            ->where('meeting_requests.status', 'confirmed')
            ->whereRaw('DATE(meeting_requests.confirmed_date) BETWEEN ? AND ?', [$firstDay, $lastDay])
            ->select([
                'meeting_requests.id',
                'students.student_name',
                'users.full_name as staff_name',
                'meeting_requests.purpose',
                'meeting_requests.purpose_detail',
                'meeting_requests.meeting_notes',
                'meeting_requests.confirmed_date',
                'meeting_requests.is_completed',
            ])
            ->orderBy('meeting_requests.confirmed_date')
            ->get();

        foreach ($meetingRows as $mt) {
            $date = Carbon::parse($mt->confirmed_date)->toDateString();
            $meetings[$date][] = [
                'id' => $mt->id,
                'student_name' => $mt->student_name,
                'staff_name' => $mt->staff_name,
                'purpose' => $mt->purpose,
                'purpose_detail' => $mt->purpose_detail,
                'meeting_notes' => $mt->meeting_notes ?? '',
                'time' => Carbon::parse($mt->confirmed_date)->format('H:i'),
                'confirmed_date' => $mt->confirmed_date,
                'is_completed' => (bool) $mt->is_completed,
            ];
        }

        return [
            'holidays' => (object) $holidays,
            'events' => (object) $events,
            'schedules' => (object) $schedules,
            'notes' => (object) $notes,
            'makeup_days' => (object) $makeupDays,
            'absence_days' => (object) $absenceDays,
            'additional_days' => (object) $additionalDays,
            'meetings' => (object) $meetings,
            'school_holiday_activities' => (object) $schoolHolidayActivities,
        ];
    }

    /**
     * 保護者に紐づく生徒一覧を取得
     */
    public function students(Request $request): JsonResponse
    {
        $user = $request->user();

        $students = $user->students()
            ->with('classroom:id,classroom_name')
            ->get(['id', 'student_name', 'classroom_id', 'grade_level', 'birth_date', 'status']);

        return response()->json([
            'success' => true,
            'data'    => $students,
        ]);
    }
}
