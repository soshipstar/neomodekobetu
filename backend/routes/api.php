<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| KIDURI 2026 REST API ルート定義
| 仕様書 Section 4 に基づくエンドポイント設計
|
*/

// ==========================================================================
// 4.1 認証 API (Public)
// ==========================================================================
Route::prefix('auth')->group(function () {
    Route::post('/login', [App\Http\Controllers\Auth\AuthController::class, 'login']);
    Route::post('/logout', [App\Http\Controllers\Auth\AuthController::class, 'logout'])
        ->middleware('auth:sanctum');
    Route::post('/refresh', [App\Http\Controllers\Auth\AuthController::class, 'refresh'])
        ->middleware('auth:sanctum');
    Route::get('/me', [App\Http\Controllers\Auth\AuthController::class, 'me'])
        ->middleware('auth:sanctum');
    Route::post('/password/reset', [App\Http\Controllers\Auth\AuthController::class, 'resetPassword']);
});

// ==========================================================================
// 4.2 管理者 API (auth:sanctum + user_type:admin)
// ==========================================================================
Route::prefix('admin')
    ->middleware(['auth:sanctum', 'user_type:admin'])
    ->group(function () {
        // --- ダッシュボード (#1) ---
        Route::get('/dashboard', [App\Http\Controllers\Admin\DashboardController::class, 'index']);

        // 教室管理
        Route::apiResource('classrooms', App\Http\Controllers\Admin\ClassroomController::class);

        // ユーザー管理
        Route::apiResource('users', App\Http\Controllers\Admin\UserController::class);
        Route::post('/users/bulk-register', [App\Http\Controllers\Admin\UserController::class, 'bulkRegister']);

        // 生徒管理
        Route::apiResource('students', App\Http\Controllers\Admin\StudentController::class);

        // システム設定
        Route::get('/settings', [App\Http\Controllers\Admin\SettingController::class, 'index']);
        Route::put('/settings', [App\Http\Controllers\Admin\SettingController::class, 'update']);

        // 監査ログ
        Route::get('/audit-logs', [App\Http\Controllers\Admin\AuditLogController::class, 'index']);
        Route::get('/error-logs', [App\Http\Controllers\Admin\ErrorLogController::class, 'index']);
        Route::get('/error-logs/summary', [App\Http\Controllers\Admin\ErrorLogController::class, 'summary']);
        Route::get('/error-logs/{errorLog}', [App\Http\Controllers\Admin\ErrorLogController::class, 'show']);
        Route::delete('/error-logs/cleanup', [App\Http\Controllers\Admin\ErrorLogController::class, 'cleanup']);

        // --- イベント管理 ---
        Route::apiResource('events', App\Http\Controllers\Admin\EventController::class);

        // --- 休日管理 ---
        Route::get('/holidays', [App\Http\Controllers\Admin\HolidayController::class, 'index']);
        Route::post('/holidays', [App\Http\Controllers\Admin\HolidayController::class, 'store']);
        Route::delete('/holidays/{holiday}', [App\Http\Controllers\Admin\HolidayController::class, 'destroy']);

        // --- デイリールーティン管理 ---
        Route::apiResource('daily-routines', App\Http\Controllers\Admin\DailyRoutineController::class);

        // --- タブレットアカウント管理 ---
        Route::get('/tablet-accounts', [App\Http\Controllers\Admin\TabletAccountController::class, 'index']);
        Route::post('/tablet-accounts', [App\Http\Controllers\Admin\TabletAccountController::class, 'store']);
        Route::put('/tablet-accounts/{account}', [App\Http\Controllers\Admin\TabletAccountController::class, 'update']);
        Route::post('/tablet-accounts/{account}/toggle', [App\Http\Controllers\Admin\TabletAccountController::class, 'toggle']);

        // --- 待機リスト管理 ---
        Route::get('/waiting-list', [App\Http\Controllers\Admin\WaitingListController::class, 'index']);
        Route::put('/waiting-list/{student}', [App\Http\Controllers\Admin\WaitingListController::class, 'update']);

        // --- 保護者管理 ---
        Route::apiResource('guardians', App\Http\Controllers\Admin\GuardianController::class);

        // --- スタッフアカウント管理 (#2-5) ---
        Route::get('/staff-accounts', [App\Http\Controllers\Admin\StaffAccountController::class, 'index']);
        Route::post('/staff-accounts', [App\Http\Controllers\Admin\StaffAccountController::class, 'store']);
        Route::get('/staff-accounts/{user}', [App\Http\Controllers\Admin\StaffAccountController::class, 'show']);
        Route::put('/staff-accounts/{user}', [App\Http\Controllers\Admin\StaffAccountController::class, 'update']);
        Route::delete('/staff-accounts/{user}', [App\Http\Controllers\Admin\StaffAccountController::class, 'destroy']);
        Route::post('/staff-accounts/{user}/convert-to-admin', [App\Http\Controllers\Admin\StaffAccountController::class, 'convertToAdmin']);

        // --- 管理者アカウント管理 (#6-7) ---
        Route::get('/admin-accounts', [App\Http\Controllers\Admin\AdminAccountController::class, 'index']);
        Route::post('/admin-accounts', [App\Http\Controllers\Admin\AdminAccountController::class, 'store']);
        Route::get('/admin-accounts/{user}', [App\Http\Controllers\Admin\AdminAccountController::class, 'show']);
        Route::put('/admin-accounts/{user}', [App\Http\Controllers\Admin\AdminAccountController::class, 'update']);
        Route::delete('/admin-accounts/{user}', [App\Http\Controllers\Admin\AdminAccountController::class, 'destroy']);
        Route::post('/admin-accounts/{user}/convert-to-staff', [App\Http\Controllers\Admin\AdminAccountController::class, 'convertToStaff']);

        // --- スタッフ管理（配置・シフト） (#8-9) ---
        Route::get('/staff', [App\Http\Controllers\Admin\StaffManagementController::class, 'index']);
        Route::get('/staff/{user}', [App\Http\Controllers\Admin\StaffManagementController::class, 'show']);
        Route::put('/staff/{user}', [App\Http\Controllers\Admin\StaffManagementController::class, 'update']);
        Route::delete('/staff/{user}', [App\Http\Controllers\Admin\StaffManagementController::class, 'destroy']);

        // --- 教室設定 (#10-11) ---
        Route::get('/classroom-settings', [App\Http\Controllers\Admin\ClassroomSettingController::class, 'index']);
        Route::put('/classroom-settings', [App\Http\Controllers\Admin\ClassroomSettingController::class, 'update']);

        // --- 一括登録（管理者用プロキシ） (#12-13) ---
        Route::post('/bulk-register/parse', [App\Http\Controllers\Staff\BulkRegisterController::class, 'parse']);
        Route::post('/bulk-register/execute', [App\Http\Controllers\Staff\BulkRegisterController::class, 'execute']);
    });

// ==========================================================================
// 4.3 スタッフ API (auth:sanctum + user_type:staff,admin)
// ==========================================================================
Route::prefix('staff')
    ->middleware(['auth:sanctum', 'user_type:staff,admin'])
    ->group(function () {
        // ダッシュボード
        Route::get('/dashboard/summary', [App\Http\Controllers\Staff\DashboardController::class, 'summary']);
        Route::get('/dashboard/calendar', [App\Http\Controllers\Staff\DashboardController::class, 'calendar']);
        Route::get('/dashboard/attendance', [App\Http\Controllers\Staff\DashboardController::class, 'attendance']);
        Route::get('/dashboard', [App\Http\Controllers\Staff\DashboardController::class, 'index']);

        // --- チャット ---
        Route::prefix('chat')->group(function () {
            Route::get('/rooms', [App\Http\Controllers\Staff\ChatController::class, 'rooms']);
            Route::get('/rooms/{room}/messages', [App\Http\Controllers\Staff\ChatController::class, 'messages']);
            Route::post('/rooms/{room}/messages', [App\Http\Controllers\Staff\ChatController::class, 'sendMessage']);
            Route::post('/rooms/{room}/pin', [App\Http\Controllers\Staff\ChatController::class, 'togglePin']);
            Route::post('/rooms/{room}/read', [App\Http\Controllers\Staff\ChatController::class, 'markRead']);
            Route::post('/broadcast', [App\Http\Controllers\Staff\ChatController::class, 'broadcast']);
        });

        // --- 生徒管理 ---
        // --- 待機児童 ---
        Route::get('/waiting-list', [App\Http\Controllers\Admin\WaitingListController::class, 'index']);
        Route::get('/waiting-list/summary', [App\Http\Controllers\Admin\WaitingListController::class, 'summary']);
        Route::put('/waiting-list/{student}', [App\Http\Controllers\Admin\WaitingListController::class, 'update']);

        Route::get('/students', [App\Http\Controllers\Staff\StudentController::class, 'index']);
        Route::post('/students', [App\Http\Controllers\Staff\StudentController::class, 'store']);
        Route::get('/students/guardians', [App\Http\Controllers\Staff\StudentController::class, 'guardians']);
        Route::get('/students/{student}', [App\Http\Controllers\Staff\StudentController::class, 'show']);
        Route::put('/students/{student}', [App\Http\Controllers\Staff\StudentController::class, 'update']);
        Route::delete('/students/{student}', [App\Http\Controllers\Staff\StudentController::class, 'destroy']);

        // --- 支援計画 ---
        Route::get('/students/{student}/support-plans', [App\Http\Controllers\Staff\SupportPlanController::class, 'index']);
        Route::post('/students/{student}/support-plans', [App\Http\Controllers\Staff\SupportPlanController::class, 'store']);
        Route::put('/students/{student}/support-plans/{plan}', [App\Http\Controllers\Staff\SupportPlanController::class, 'updateNested']); // (#31,39) nested alias
        Route::post('/students/{student}/support-plans/ai-generate', [App\Http\Controllers\Staff\SupportPlanController::class, 'generateAiForStudent']); // (#32)
        Route::get('/support-plans/{plan}', [App\Http\Controllers\Staff\SupportPlanController::class, 'show']);
        Route::put('/support-plans/{plan}', [App\Http\Controllers\Staff\SupportPlanController::class, 'update']);
        Route::delete('/support-plans/{plan}', [App\Http\Controllers\Staff\SupportPlanController::class, 'destroy']);
        Route::post('/support-plans/{plan}/generate-ai', [App\Http\Controllers\Staff\SupportPlanController::class, 'generateAi']);
        Route::post('/support-plans/{plan}/sign', [App\Http\Controllers\Staff\SupportPlanController::class, 'sign']);
        Route::get('/support-plans/{plan}/pdf', [App\Http\Controllers\Staff\SupportPlanController::class, 'pdf']);
        Route::get('/support-plans/{plan}/export', [App\Http\Controllers\Staff\SupportPlanController::class, 'export']);
        Route::post('/support-plans/{plan}/publish', [App\Http\Controllers\Staff\SupportPlanController::class, 'publish']);
        Route::post('/support-plans/{plan}/make-official', [App\Http\Controllers\Staff\SupportPlanController::class, 'makeOfficial']);
        Route::get('/support-plans/{plan}/basis', [App\Http\Controllers\Staff\SupportPlanController::class, 'basis']);
        Route::post('/support-plans/{plan}/generate-basis', [App\Http\Controllers\Staff\SupportPlanController::class, 'generateBasis']);
        Route::post('/students/{student}/generate-wish', [App\Http\Controllers\Staff\SupportPlanController::class, 'generateWishFromInterview']);

        // --- モニタリング ---
        Route::get('/students/{student}/monitoring', [App\Http\Controllers\Staff\MonitoringController::class, 'index']);
        Route::post('/students/{student}/monitoring', [App\Http\Controllers\Staff\MonitoringController::class, 'store']);
        Route::get('/monitoring/{monitoring}', [App\Http\Controllers\Staff\MonitoringController::class, 'show']);
        Route::put('/monitoring/{monitoring}', [App\Http\Controllers\Staff\MonitoringController::class, 'update']);
        Route::delete('/monitoring/{monitoring}', [App\Http\Controllers\Staff\MonitoringController::class, 'destroy']);
        Route::post('/monitoring/{monitoring}/sign', [App\Http\Controllers\Staff\MonitoringController::class, 'sign']);
        Route::post('/monitoring/generate', [App\Http\Controllers\Staff\MonitoringController::class, 'generate']);
        Route::post('/monitoring/{monitoring}/generate-ai', [App\Http\Controllers\Staff\MonitoringController::class, 'generateAi']);
        Route::get('/monitoring/{monitoring}/pdf', [App\Http\Controllers\Staff\MonitoringController::class, 'pdf']);

        // --- かけはし ---
        Route::get('/students/{student}/kakehashi', [App\Http\Controllers\Staff\KakehashiController::class, 'index']);
        Route::post('/kakehashi/generate', [App\Http\Controllers\Staff\KakehashiController::class, 'generate']);
        Route::post('/kakehashi/{period}', [App\Http\Controllers\Staff\KakehashiController::class, 'store']);
        Route::put('/kakehashi/{period}', [App\Http\Controllers\Staff\KakehashiController::class, 'update']);
        Route::get('/kakehashi/{period}/pdf', [App\Http\Controllers\Staff\KakehashiController::class, 'pdf']);
        Route::post('/kakehashi/{period}/toggle-guardian-hidden', [App\Http\Controllers\Staff\KakehashiController::class, 'toggleGuardianHidden']);

        // --- 連絡帳 (日常活動記録) ---
        Route::get('/renrakucho', [App\Http\Controllers\Staff\RenrakuchoController::class, 'index']);
        Route::post('/renrakucho', [App\Http\Controllers\Staff\RenrakuchoController::class, 'store']);
        Route::put('/renrakucho/{record}', [App\Http\Controllers\Staff\RenrakuchoController::class, 'update']);
        Route::delete('/renrakucho/{record}', [App\Http\Controllers\Staff\RenrakuchoController::class, 'destroy']);
        Route::get('/renrakucho/{record}/student-records', [App\Http\Controllers\Staff\RenrakuchoController::class, 'studentRecords']);
        Route::post('/renrakucho/{record}/student-records', [App\Http\Controllers\Staff\RenrakuchoController::class, 'storeStudentRecords']);
        Route::post('/renrakucho/{record}/send-to-guardians', [App\Http\Controllers\Staff\RenrakuchoController::class, 'sendToGuardians']);
        Route::post('/renrakucho/{record}/generate-integrated', [App\Http\Controllers\Staff\RenrakuchoController::class, 'generateIntegrated']);
        Route::post('/renrakucho/{record}/save-draft', [App\Http\Controllers\Staff\RenrakuchoController::class, 'saveDraft']);
        Route::post('/renrakucho/{record}/regenerate-integrated', [App\Http\Controllers\Staff\RenrakuchoController::class, 'regenerateIntegrated']);
        Route::get('/renrakucho/{record}/view-integrated', [App\Http\Controllers\Staff\RenrakuchoController::class, 'viewIntegrated']);

        // --- お便り ---
        Route::get('/newsletter-settings', [App\Http\Controllers\Staff\NewsletterSettingController::class, 'show']);
        Route::put('/newsletter-settings', [App\Http\Controllers\Staff\NewsletterSettingController::class, 'update']);
        Route::apiResource('newsletters', App\Http\Controllers\Staff\NewsletterController::class);
        Route::post('/newsletters/{newsletter}/generate-ai', [App\Http\Controllers\Staff\NewsletterController::class, 'generateAi']);
        Route::post('/newsletters/{newsletter}/publish', [App\Http\Controllers\Staff\NewsletterController::class, 'publish']);
        Route::get('/newsletters/{newsletter}/pdf', [App\Http\Controllers\Staff\NewsletterController::class, 'pdf']);
        Route::post('/newsletters/pdf-preview', [App\Http\Controllers\Staff\NewsletterController::class, 'pdfPreview']);

        // --- 面談 ---
        Route::apiResource('meetings', App\Http\Controllers\Staff\MeetingController::class)
            ->except(['destroy']);

        // --- 出欠 ---
        Route::get('/attendance', [App\Http\Controllers\Staff\AttendanceController::class, 'index']);
        Route::put('/absence/{absence}/makeup', [App\Http\Controllers\Staff\AttendanceController::class, 'approveMakeup']);
        Route::put('/absence/{absence}/note', [App\Http\Controllers\Staff\AttendanceController::class, 'updateMakeupNote']);

        // --- 週間計画 ---
        Route::get('/weekly-plans', [App\Http\Controllers\Staff\WeeklyPlanController::class, 'index']);
        Route::post('/weekly-plans', [App\Http\Controllers\Staff\WeeklyPlanController::class, 'store']);
        Route::get('/weekly-plans/{studentId}', [App\Http\Controllers\Staff\WeeklyPlanController::class, 'show'])->where('studentId', '[0-9]+');
        Route::put('/weekly-plans/{plan}', [App\Http\Controllers\Staff\WeeklyPlanController::class, 'update']);
        Route::get('/weekly-plans/{plan}/pdf', [App\Http\Controllers\Staff\WeeklyPlanController::class, 'pdf']);

        // --- 業務日誌 ---
        Route::get('/work-diary', [App\Http\Controllers\Staff\WorkDiaryController::class, 'index']);
        Route::get('/work-diary/{diary}', [App\Http\Controllers\Staff\WorkDiaryController::class, 'show']);
        Route::post('/work-diary', [App\Http\Controllers\Staff\WorkDiaryController::class, 'store']);
        Route::put('/work-diary/{diary}', [App\Http\Controllers\Staff\WorkDiaryController::class, 'update']);

        // --- 面談記録 ---
        Route::get('/student-interviews', [App\Http\Controllers\Staff\StudentInterviewController::class, 'list']);
        Route::post('/students/{student}/interview', [App\Http\Controllers\Staff\StudentInterviewController::class, 'store']);
        Route::get('/students/{student}/interviews', [App\Http\Controllers\Staff\StudentInterviewController::class, 'index']);
        Route::get('/student-interviews/{interview}', [App\Http\Controllers\Staff\StudentInterviewController::class, 'showSingle']);
        Route::put('/student-interviews/{interview}', [App\Http\Controllers\Staff\StudentInterviewController::class, 'update']);
        Route::delete('/student-interviews/{interview}', [App\Http\Controllers\Staff\StudentInterviewController::class, 'destroy']);
        Route::get('/student-interviews/{interview}/pdf', [App\Http\Controllers\Staff\StudentInterviewController::class, 'pdf']);

        // --- 未対応タスク ---
        Route::get('/pending-tasks', [App\Http\Controllers\Staff\PendingTaskController::class, 'index']);
        Route::post('/pending-tasks/{id}/complete', [App\Http\Controllers\Staff\PendingTaskController::class, 'complete']); // (#25)

        // --- 施設評価（スタッフ閲覧用） ---
        Route::get('/facility-evaluation/periods', [App\Http\Controllers\Staff\FacilityEvaluationController::class, 'periods']);
        Route::post('/facility-evaluation/periods', [App\Http\Controllers\Staff\FacilityEvaluationController::class, 'createPeriod']);
        Route::put('/facility-evaluation/periods/{period}', [App\Http\Controllers\Staff\FacilityEvaluationController::class, 'updatePeriod']);
        Route::get('/facility-evaluation/periods/{period}/status', [App\Http\Controllers\Staff\FacilityEvaluationController::class, 'responseStatus']);
        Route::get('/facility-evaluation/summary', [App\Http\Controllers\Staff\FacilityEvaluationController::class, 'summary']);
        Route::get('/facility-evaluation/responses', [App\Http\Controllers\Staff\FacilityEvaluationController::class, 'responses']);
        Route::get('/facility-evaluation/responses/{evaluation}/pdf', [App\Http\Controllers\Staff\FacilityEvaluationController::class, 'responsePdf']);
        Route::get('/facility-evaluation/self-summary', [App\Http\Controllers\Staff\FacilityEvaluationController::class, 'selfSummary']);
        Route::get('/facility-evaluation/staff-evaluation', [App\Http\Controllers\Staff\FacilityEvaluationController::class, 'staffEvaluation']);
        Route::post('/facility-evaluation/staff-evaluation', [App\Http\Controllers\Staff\FacilityEvaluationController::class, 'saveStaffEvaluation']);

        // --- 学校休業日活動 ---
        Route::get('/school-holiday-activities', [App\Http\Controllers\Staff\SchoolHolidayActivityController::class, 'index']);
        Route::post('/school-holiday-activities/batch', [App\Http\Controllers\Staff\SchoolHolidayActivityController::class, 'batch']);
        Route::post('/school-holiday-activities', [App\Http\Controllers\Staff\SchoolHolidayActivityController::class, 'store']);
        Route::put('/school-holiday-activities/{activity}', [App\Http\Controllers\Staff\SchoolHolidayActivityController::class, 'update']);
        Route::delete('/school-holiday-activities/{activity}', [App\Http\Controllers\Staff\SchoolHolidayActivityController::class, 'destroy']);
        Route::post('/school-holiday-activities/{activity}/assign', [App\Http\Controllers\Staff\SchoolHolidayActivityController::class, 'assign']); // (#28)

        // --- タグ設定 ---
        Route::get('/tag-settings', [App\Http\Controllers\Staff\TagSettingController::class, 'index']);
        Route::post('/tag-settings', [App\Http\Controllers\Staff\TagSettingController::class, 'store']);
        Route::put('/tag-settings/{tag}', [App\Http\Controllers\Staff\TagSettingController::class, 'update']);
        Route::delete('/tag-settings/{tag}', [App\Http\Controllers\Staff\TagSettingController::class, 'destroy']);
        Route::post('/tag-settings/reorder', [App\Http\Controllers\Staff\TagSettingController::class, 'reorder']); // (#27)

        // --- 非表示ドキュメント ---
        Route::get('/hidden-documents', [App\Http\Controllers\Staff\HiddenDocumentController::class, 'index']);
        Route::post('/hidden-documents/toggle', [App\Http\Controllers\Staff\HiddenDocumentController::class, 'toggle']);

        // --- 生徒チャット（スタッフ管理） ---
        Route::get('/student-chats', [App\Http\Controllers\Staff\StaffStudentChatController::class, 'rooms']);
        Route::get('/student-chats/{room}/messages', [App\Http\Controllers\Staff\StaffStudentChatController::class, 'messages']);
        Route::post('/student-chats/{room}/messages', [App\Http\Controllers\Staff\StaffStudentChatController::class, 'sendMessage']);
        Route::post('/student-chats/broadcast', [App\Http\Controllers\Staff\StaffStudentChatController::class, 'broadcast']);
        Route::delete('/student-chats/messages/{message}', [App\Http\Controllers\Staff\StaffStudentChatController::class, 'deleteMessage']);

        // --- 追加利用 ---
        Route::get('/additional-usage', [App\Http\Controllers\Staff\AdditionalUsageController::class, 'index']);
        Route::post('/additional-usage', [App\Http\Controllers\Staff\AdditionalUsageController::class, 'store']);
        Route::post('/additional-usage/batch', [App\Http\Controllers\Staff\AdditionalUsageController::class, 'batchUpdate']);
        Route::get('/additional-usage/student-month', [App\Http\Controllers\Staff\AdditionalUsageController::class, 'studentMonth']);
        Route::delete('/additional-usage/{usage}', [App\Http\Controllers\Staff\AdditionalUsageController::class, 'destroy']);

        // --- 提出物管理 ---
        Route::get('/submissions', [App\Http\Controllers\Staff\StaffSubmissionController::class, 'index']);
        Route::get('/submissions/{request}', [App\Http\Controllers\Staff\StaffSubmissionController::class, 'show']);
        Route::post('/submissions', [App\Http\Controllers\Staff\StaffSubmissionController::class, 'store']);
        Route::put('/submissions/{request}', [App\Http\Controllers\Staff\StaffSubmissionController::class, 'update']);
        Route::delete('/submissions/{request}', [App\Http\Controllers\Staff\StaffSubmissionController::class, 'destroy']); // (#29)
        Route::get('/submissions/{request}/students', [App\Http\Controllers\Staff\StaffSubmissionController::class, 'students']); // (#30)

        // --- 生徒ログイン印刷 ---
        Route::get('/student-login-print/{student}', [App\Http\Controllers\Staff\StudentLoginPrintController::class, 'show']);

        // --- 保護者管理（スタッフレベル） ---
        Route::get('/guardians', [App\Http\Controllers\Staff\StaffGuardianController::class, 'index']);
        Route::post('/guardians', [App\Http\Controllers\Staff\StaffGuardianController::class, 'store']);
        Route::get('/guardians/{guardian}', [App\Http\Controllers\Staff\StaffGuardianController::class, 'show']);
        Route::put('/guardians/{guardian}', [App\Http\Controllers\Staff\StaffGuardianController::class, 'update']);
        Route::delete('/guardians/{guardian}', [App\Http\Controllers\Staff\StaffGuardianController::class, 'destroy']);

        // --- 未確認連絡帳 ---
        Route::get('/unconfirmed-notes', [App\Http\Controllers\Staff\UnconfirmedNoteController::class, 'index']);
        Route::post('/unconfirmed-notes/{note}/send', [App\Http\Controllers\Staff\UnconfirmedNoteController::class, 'send']);

        // --- イベント（スタッフレベル） ---
        Route::get('/events', [App\Http\Controllers\Staff\StaffEventController::class, 'index']);
        Route::post('/events', [App\Http\Controllers\Staff\StaffEventController::class, 'store']);
        Route::put('/events/{event}', [App\Http\Controllers\Staff\StaffEventController::class, 'update']);
        Route::delete('/events/{event}', [App\Http\Controllers\Staff\StaffEventController::class, 'destroy']);
        Route::post('/events/{event}/register', [App\Http\Controllers\Staff\StaffEventController::class, 'register']);
        Route::get('/events/{event}/registrations', [App\Http\Controllers\Staff\StaffEventController::class, 'registrations']); // (#24)

        // --- 休日（スタッフレベル） ---
        Route::get('/holidays', [App\Http\Controllers\Staff\StaffHolidayController::class, 'index']);
        Route::post('/holidays', [App\Http\Controllers\Staff\StaffHolidayController::class, 'store']);
        Route::delete('/holidays/{holiday}', [App\Http\Controllers\Staff\StaffHolidayController::class, 'destroy']);

        // --- デイリールーティン ---
        Route::get('/daily-routines', [App\Http\Controllers\Staff\DailyRoutineController::class, 'index']);
        Route::post('/daily-routines', [App\Http\Controllers\Staff\DailyRoutineController::class, 'store']);
        Route::put('/daily-routines/{routine}', [App\Http\Controllers\Staff\DailyRoutineController::class, 'update']);
        Route::delete('/daily-routines/{routine}', [App\Http\Controllers\Staff\DailyRoutineController::class, 'destroy']);
        Route::post('/daily-routines/reorder', [App\Http\Controllers\Staff\DailyRoutineController::class, 'reorder']); // (#26)

        // --- 一括登録 ---
        Route::post('/bulk-register/parse', [App\Http\Controllers\Staff\BulkRegisterController::class, 'parse']);
        Route::post('/bulk-register/execute', [App\Http\Controllers\Staff\BulkRegisterController::class, 'execute']);

        // --- 支援案（活動支援計画） ---
        Route::get('/activity-support-plans', [App\Http\Controllers\Staff\ActivitySupportPlanController::class, 'index']);
        Route::get('/activity-support-plans/past', [App\Http\Controllers\Staff\ActivitySupportPlanController::class, 'pastPlans']);
        Route::post('/activity-support-plans', [App\Http\Controllers\Staff\ActivitySupportPlanController::class, 'store']);
        Route::get('/activity-support-plans/{plan}', [App\Http\Controllers\Staff\ActivitySupportPlanController::class, 'show']);
        Route::get('/activity-support-plans/{plan}/pdf', [App\Http\Controllers\Staff\ActivitySupportPlanController::class, 'pdf']);
        Route::put('/activity-support-plans/{plan}', [App\Http\Controllers\Staff\ActivitySupportPlanController::class, 'update']);
        Route::delete('/activity-support-plans/{plan}', [App\Http\Controllers\Staff\ActivitySupportPlanController::class, 'destroy']);
        Route::post('/activity-support-plans/generate-ai/five-domains', [App\Http\Controllers\Staff\ActivitySupportPlanController::class, 'generateAiFiveDomains']);
        Route::post('/activity-support-plans/generate-ai/schedule-content', [App\Http\Controllers\Staff\ActivitySupportPlanController::class, 'generateAiScheduleContent']);

        // --- スタッフ間チャット ---
        Route::prefix('staff-chat')->group(function () {
            Route::get('/staff-list', [App\Http\Controllers\Staff\StaffChatController::class, 'staffList']);
            Route::get('/rooms', [App\Http\Controllers\Staff\StaffChatController::class, 'rooms']);
            Route::post('/rooms', [App\Http\Controllers\Staff\StaffChatController::class, 'createRoom']);
            Route::get('/rooms/{room}/messages', [App\Http\Controllers\Staff\StaffChatController::class, 'messages']);
            Route::post('/rooms/{room}/messages', [App\Http\Controllers\Staff\StaffChatController::class, 'sendMessage']);
            Route::get('/rooms/{room}/members', [App\Http\Controllers\Staff\StaffChatController::class, 'members']);
        });

        // --- お知らせ ---
        Route::get('/announcements', [App\Http\Controllers\Staff\AnnouncementController::class, 'index']);
        Route::post('/announcements', [App\Http\Controllers\Staff\AnnouncementController::class, 'store']);
        Route::put('/announcements/{announcement}', [App\Http\Controllers\Staff\AnnouncementController::class, 'update']);
        Route::delete('/announcements/{announcement}', [App\Http\Controllers\Staff\AnnouncementController::class, 'destroy']);
        Route::post('/announcements/{announcement}/publish', [App\Http\Controllers\Staff\AnnouncementController::class, 'publish']);
        Route::post('/announcements/{announcement}/unpublish', [App\Http\Controllers\Staff\AnnouncementController::class, 'unpublish']);

        // --- プロフィール ---
        Route::get('/profile', [App\Http\Controllers\Staff\StaffProfileController::class, 'show']);
        Route::put('/profile', [App\Http\Controllers\Staff\StaffProfileController::class, 'update']);
        Route::put('/profile/password', [App\Http\Controllers\Staff\StaffProfileController::class, 'changePassword']);
    });

// ==========================================================================
// 4.4 保護者 API (auth:sanctum + user_type:guardian)
// ==========================================================================
Route::prefix('guardian')
    ->middleware(['auth:sanctum', 'user_type:guardian'])
    ->group(function () {
        // ダッシュボード
        Route::get('/dashboard', [App\Http\Controllers\Guardian\DashboardController::class, 'index']);

        // --- 生徒一覧 (#14, #15) ---
        Route::get('/students', [App\Http\Controllers\Guardian\DashboardController::class, 'students']);
        Route::get('/children', [App\Http\Controllers\Guardian\DashboardController::class, 'students']); // alias

        // --- 生徒ごとのネストルート (#16) ---
        Route::get('/students/{student}/kakehashi', [App\Http\Controllers\Guardian\KakehashiController::class, 'index']);
        Route::get('/students/{student}/monitoring', [App\Http\Controllers\Guardian\GuardianMonitoringController::class, 'index']);
        Route::get('/students/{student}/notes', [App\Http\Controllers\Guardian\GuardianNoteController::class, 'index']);
        Route::get('/students/{student}/weekly-plans', [App\Http\Controllers\Guardian\GuardianWeeklyPlanController::class, 'index']);

        // チャット
        Route::get('/chat/rooms/{room}/messages', [App\Http\Controllers\Guardian\ChatController::class, 'messages']);
        Route::post('/chat/rooms/{room}/messages', [App\Http\Controllers\Guardian\ChatController::class, 'sendMessage']);

        // 支援計画
        Route::get('/support-plans', [App\Http\Controllers\Guardian\SupportPlanController::class, 'index']);
        Route::post('/support-plans/{plan}/review', [App\Http\Controllers\Guardian\SupportPlanController::class, 'review']);
        Route::post('/support-plans/{plan}/sign', [App\Http\Controllers\Guardian\SupportPlanController::class, 'sign']);
        Route::post('/support-plans/{plan}/comment', [App\Http\Controllers\Guardian\SupportPlanController::class, 'addComment']);

        // かけはし
        Route::get('/kakehashi', [App\Http\Controllers\Guardian\KakehashiController::class, 'index']);
        Route::get('/kakehashi/history', [App\Http\Controllers\Guardian\KakehashiController::class, 'history']); // (#17)
        Route::get('/kakehashi/history/{period}', [App\Http\Controllers\Guardian\KakehashiController::class, 'historyDetail']); // (#18)
        Route::post('/kakehashi/confirm-staff', [App\Http\Controllers\Guardian\KakehashiController::class, 'confirmStaff']); // (#19)
        Route::get('/kakehashi/{period}', [App\Http\Controllers\Guardian\KakehashiController::class, 'show']);
        Route::post('/kakehashi/{period}', [App\Http\Controllers\Guardian\KakehashiController::class, 'store']);
        Route::post('/kakehashi/{period}/entry', [App\Http\Controllers\Guardian\KakehashiController::class, 'entry']); // (#20)

        // 欠席連絡
        Route::post('/absence', [App\Http\Controllers\Guardian\AbsenceController::class, 'store']);
        Route::get('/absence', [App\Http\Controllers\Guardian\AbsenceController::class, 'index']);
        Route::get('/absences', [App\Http\Controllers\Guardian\AbsenceController::class, 'index']); // (#21) plural alias
        Route::post('/absences', [App\Http\Controllers\Guardian\AbsenceController::class, 'store']); // (#22) plural alias

        // 面談
        Route::get('/meetings', [App\Http\Controllers\Guardian\MeetingController::class, 'index']);
        Route::get('/meetings/{meeting}', [App\Http\Controllers\Guardian\MeetingController::class, 'show']);
        Route::put('/meetings/{meeting}', [App\Http\Controllers\Guardian\MeetingController::class, 'update']);
        Route::post('/meetings/{meeting}/respond', [App\Http\Controllers\Guardian\MeetingController::class, 'respond']); // (#23)

        // 施設評価
        Route::post('/evaluation', [App\Http\Controllers\Guardian\FacilityEvaluationController::class, 'store']);
        Route::get('/evaluation', [App\Http\Controllers\Guardian\FacilityEvaluationController::class, 'index']);

        // お便り
        Route::get('/newsletters', [App\Http\Controllers\Guardian\NewsletterController::class, 'index']);
        Route::get('/newsletters/{newsletter}', [App\Http\Controllers\Guardian\NewsletterController::class, 'show']);

        // --- 週間計画 ---
        Route::get('/weekly-plans', [App\Http\Controllers\Guardian\GuardianWeeklyPlanController::class, 'index']);
        Route::get('/weekly-plans/{plan}', [App\Http\Controllers\Guardian\GuardianWeeklyPlanController::class, 'show']);

        // --- モニタリング ---
        Route::get('/monitoring', [App\Http\Controllers\Guardian\GuardianMonitoringController::class, 'index']);
        Route::get('/monitoring/{record}', [App\Http\Controllers\Guardian\GuardianMonitoringController::class, 'show']);
        Route::post('/monitoring/{record}/confirm', [App\Http\Controllers\Guardian\GuardianMonitoringController::class, 'confirm']);

        // --- 連絡帳 / ノート ---
        Route::get('/notes', [App\Http\Controllers\Guardian\GuardianNoteController::class, 'index']);
        Route::get('/notes/{date}', [App\Http\Controllers\Guardian\GuardianNoteController::class, 'byDate']);
        Route::post('/notes/{note}/confirm', [App\Http\Controllers\Guardian\GuardianNoteController::class, 'confirm']);

        // --- 連絡帳一覧・検索 (SC-008) ---
        Route::get('/communication-logs', [App\Http\Controllers\Guardian\CommunicationLogController::class, 'index']);

        // --- お知らせ ---
        Route::get('/announcements', [App\Http\Controllers\Guardian\AnnouncementController::class, 'index']);
        Route::post('/announcements/{announcement}/read', [App\Http\Controllers\Guardian\AnnouncementController::class, 'markRead']);

        // --- プロフィール ---
        Route::get('/profile', [App\Http\Controllers\Guardian\GuardianProfileController::class, 'show']);
        Route::put('/profile', [App\Http\Controllers\Guardian\GuardianProfileController::class, 'update']);
        Route::put('/profile/password', [App\Http\Controllers\Guardian\GuardianProfileController::class, 'changePassword']);
    });

// ==========================================================================
// 4.5 生徒 API (auth:sanctum + user_type:student)
// ==========================================================================
Route::prefix('student')
    ->middleware(['auth:sanctum', 'user_type:student'])
    ->group(function () {
        // ダッシュボード
        Route::get('/dashboard', [App\Http\Controllers\Student\DashboardController::class, 'index']);

        // チャット
        Route::get('/chat/messages', [App\Http\Controllers\Student\ChatController::class, 'messages']);
        Route::post('/chat/messages', [App\Http\Controllers\Student\ChatController::class, 'sendMessage']);

        // 提出物
        Route::get('/submissions', [App\Http\Controllers\Student\SubmissionController::class, 'index']);
        Route::post('/submissions', [App\Http\Controllers\Student\SubmissionController::class, 'store']);
        Route::post('/submissions/complete', [App\Http\Controllers\Student\SubmissionController::class, 'complete']);
        Route::post('/submissions/uncomplete', [App\Http\Controllers\Student\SubmissionController::class, 'uncomplete']);
        Route::delete('/submissions/{id}', [App\Http\Controllers\Student\SubmissionController::class, 'destroy']);

        // --- 週間計画 ---
        Route::get('/weekly-plans', [App\Http\Controllers\Student\StudentWeeklyPlanController::class, 'index']);
        Route::post('/weekly-plans/{plan}/save', [App\Http\Controllers\Student\StudentWeeklyPlanController::class, 'save']);
        Route::put('/weekly-plans/{plan}', [App\Http\Controllers\Student\StudentWeeklyPlanController::class, 'update']); // (#41)

        // --- スケジュール ---
        Route::get('/schedule', [App\Http\Controllers\Student\StudentScheduleController::class, 'index']);

        // --- プロフィール/パスワード ---
        Route::get('/profile', [App\Http\Controllers\Student\StudentProfileController::class, 'show']);
        Route::put('/profile/password', [App\Http\Controllers\Student\StudentProfileController::class, 'changePassword']);

        // --- 支援計画コメント ---
        Route::post('/support-plans/{plan}/comment', [App\Http\Controllers\Student\StudentPlanCommentController::class, 'store']);
    });

// ==========================================================================
// 4.6 タブレット API (auth:sanctum + user_type:tablet,staff,admin)
// ==========================================================================
Route::prefix('tablet')
    ->middleware(['auth:sanctum', 'user_type:tablet,staff,admin'])
    ->group(function () {
        Route::get('/students', [App\Http\Controllers\Tablet\TabletController::class, 'students']);
        Route::post('/students/{student}/check-in', [App\Http\Controllers\Tablet\TabletController::class, 'checkIn']); // (#33)
        Route::post('/students/{student}/check-out', [App\Http\Controllers\Tablet\TabletController::class, 'checkOut']); // (#34)
        Route::get('/present-students', [App\Http\Controllers\Tablet\TabletController::class, 'presentStudents']); // (#35)
        Route::get('/activity-options', [App\Http\Controllers\Tablet\TabletController::class, 'activityOptions']); // (#36)
        Route::get('/activity-records', [App\Http\Controllers\Tablet\TabletController::class, 'activityRecords']); // (#37)
        Route::post('/activity-records', [App\Http\Controllers\Tablet\TabletController::class, 'storeActivity']); // (#38)
        Route::get('/activities/{date}', [App\Http\Controllers\Tablet\TabletController::class, 'activities']);
        Route::post('/activities', [App\Http\Controllers\Tablet\TabletController::class, 'storeActivity']);
        Route::put('/activities/{activity}', [App\Http\Controllers\Tablet\TabletController::class, 'updateActivity']);
        Route::delete('/activities/{activity}', [App\Http\Controllers\Tablet\TabletController::class, 'deleteActivity']);
        Route::post('/activities/integrate', [App\Http\Controllers\Tablet\TabletController::class, 'integrateActivities']);
        Route::post('/renrakucho', [App\Http\Controllers\Tablet\TabletController::class, 'storeRenrakucho']);
    });

// ==========================================================================
// 4.7 AI / ベクトル検索 API (auth:sanctum)
// ==========================================================================
Route::prefix('ai')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        Route::post('/generate/support-plan', [App\Http\Controllers\Api\AiGenerationController::class, 'generateSupportPlan']);
        Route::post('/generate/monitoring', [App\Http\Controllers\Api\AiGenerationController::class, 'generateMonitoring']);
        Route::post('/generate/newsletter', [App\Http\Controllers\Api\AiGenerationController::class, 'generateNewsletter']);
    });

// ==========================================================================
// 4.8 分析 API (auth:sanctum + user_type:staff,admin)
// ==========================================================================
Route::prefix('analytics')
    ->middleware(['auth:sanctum', 'user_type:staff,admin'])
    ->group(function () {
        Route::get('/student/{student}/growth', [App\Http\Controllers\Api\AnalyticsController::class, 'studentGrowth']);
        Route::get('/facility/evaluation', [App\Http\Controllers\Api\AnalyticsController::class, 'facilityEvaluation']);
        Route::get('/attendance/stats', [App\Http\Controllers\Api\AnalyticsController::class, 'attendanceStats']);
        Route::get('/support-plan/effectiveness', [App\Http\Controllers\Api\AnalyticsController::class, 'supportPlanEffectiveness']);
    });

// ==========================================================================
// 共通 API (auth:sanctum)
// ==========================================================================
Route::middleware('auth:sanctum')->group(function () {
    // 通知
    Route::get('/notifications', [App\Http\Controllers\Api\NotificationController::class, 'index']);
    Route::post('/notifications/{notification}/read', [App\Http\Controllers\Api\NotificationController::class, 'markRead']);
    Route::post('/notifications/read-all', [App\Http\Controllers\Api\NotificationController::class, 'markAllRead']);

    // ファイルアップロード
    Route::post('/upload', [App\Http\Controllers\Api\FileUploadController::class, 'store']);
    Route::delete('/upload/{file}', [App\Http\Controllers\Api\FileUploadController::class, 'destroy']);
    Route::get('/download/{path}', [App\Http\Controllers\Api\FileUploadController::class, 'download'])->where('path', '.*');

    // --- Web Push通知 ---
    Route::get('/push/vapid-key', [App\Http\Controllers\Api\PushSubscriptionController::class, 'vapidPublicKey']);
    Route::post('/push/subscribe', [App\Http\Controllers\Api\PushSubscriptionController::class, 'subscribe']);
    Route::post('/push/unsubscribe', [App\Http\Controllers\Api\PushSubscriptionController::class, 'unsubscribe']);
});
