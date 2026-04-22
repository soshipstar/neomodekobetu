<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\DailyRecord;
use App\Models\IntegratedNote;
use App\Models\SendHistory;
use App\Models\StudentRecord;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RenrakuchoController extends Controller
{
    /**
     * Legacy domain1/domain2 fields to new 5-domain schema mapping helper.
     * When the frontend sends domain1/domain1_content/domain2/domain2_content (legacy form),
     * this maps them to the corresponding 5-domain column.
     */
    private function mapLegacyDomainFields(array $studentData): array
    {
        $domainColumns = ['health_life', 'motor_sensory', 'cognitive_behavior', 'language_communication', 'social_relations'];

        if (!empty($studentData['domain1'] ?? '') && in_array($studentData['domain1'], $domainColumns)) {
            // Initialize all domain columns to null if not already set
            foreach ($domainColumns as $col) {
                if (!isset($studentData[$col])) {
                    $studentData[$col] = null;
                }
            }

            // Map domain1
            $domain1Key = $studentData['domain1'];
            if (!empty($studentData['domain1_content'] ?? '')) {
                $studentData[$domain1Key] = $studentData['domain1_content'];
            }

            // Map domain2
            if (!empty($studentData['domain2'] ?? '') && in_array($studentData['domain2'], $domainColumns)) {
                $domain2Key = $studentData['domain2'];
                if (!empty($studentData['domain2_content'] ?? '')) {
                    $studentData[$domain2Key] = $studentData['domain2_content'];
                }
            }

            // Map daily_note to notes
            if (isset($studentData['daily_note']) && !isset($studentData['notes'])) {
                $studentData['notes'] = $studentData['daily_note'];
            }
        }

        return $studentData;
    }

    /**
     * 連絡帳（日常活動記録）一覧を取得
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = DailyRecord::with(['staff:id,full_name'])
            ->withCount('studentRecords');

        // 教室フィルタ（DailyRecord自体のclassroom_idで絞り込む）
        $accessible = $user->accessibleClassroomIds();
        if (!empty($accessible)) {
            $query->whereIn('classroom_id', $accessible);
        }

        // 日付フィルタ
        if ($request->filled('date')) {
            $query->where('record_date', $request->date);
        }

        if ($request->filled('date_from')) {
            $query->where('record_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('record_date', '<=', $request->date_to);
        }

        $records = $query->orderByDesc('record_date')
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 30));

        // Append sent/unsent counts for each record
        $records->getCollection()->transform(function ($record) {
            $record->unsent_count = IntegratedNote::where('daily_record_id', $record->id)->where('is_sent', false)->count();
            $record->sent_count = IntegratedNote::where('daily_record_id', $record->id)->where('is_sent', true)->count();
            return $record;
        });

        return response()->json([
            'success' => true,
            'data'    => $records,
        ]);
    }

    /**
     * 日常活動記録を新規作成
     * Accepts both legacy (domain1/domain2) and new (5-domain) field formats.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'record_date'       => 'required|date',
            'activity_name'     => 'required|string|max:255',
            'common_activity'   => 'required|string',
            'support_plan_id'   => 'nullable|integer|exists:activity_support_plans,id',
            'students'          => 'required|array|min:1',
            'students.*.id'                        => 'required|exists:students,id',
            // New 5-domain fields
            'students.*.health_life'               => 'nullable|string',
            'students.*.motor_sensory'             => 'nullable|string',
            'students.*.cognitive_behavior'        => 'nullable|string',
            'students.*.language_communication'    => 'nullable|string',
            'students.*.social_relations'          => 'nullable|string',
            'students.*.notes'                     => 'nullable|string',
            // Legacy domain1/domain2 fields
            'students.*.daily_note'                => 'nullable|string',
            'students.*.domain1'                   => 'nullable|string',
            'students.*.domain1_content'           => 'nullable|string',
            'students.*.domain2'                   => 'nullable|string',
            'students.*.domain2_content'           => 'nullable|string',
        ]);

        $record = DB::transaction(function () use ($request, $validated) {
            $record = DailyRecord::create([
                'record_date'     => $validated['record_date'],
                'staff_id'        => $request->user()->id,
                'classroom_id'    => $request->user()->classroom_id,
                'activity_name'   => $validated['activity_name'],
                'common_activity' => $validated['common_activity'],
                'support_plan_id' => $validated['support_plan_id'] ?? null,
            ]);

            foreach ($validated['students'] as $studentData) {
                // Map legacy fields if present
                $studentData = $this->mapLegacyDomainFields($studentData);

                StudentRecord::create([
                    'daily_record_id'          => $record->id,
                    'student_id'               => $studentData['id'],
                    'health_life'              => $studentData['health_life'] ?? null,
                    'motor_sensory'            => $studentData['motor_sensory'] ?? null,
                    'cognitive_behavior'       => $studentData['cognitive_behavior'] ?? null,
                    'language_communication'   => $studentData['language_communication'] ?? null,
                    'social_relations'         => $studentData['social_relations'] ?? null,
                    'notes'                    => $studentData['notes'] ?? null,
                ]);
            }

            return $record;
        });

        return response()->json([
            'success' => true,
            'data'    => $record->load('studentRecords'),
            'message' => '活動を保存しました。',
        ], 201);
    }

    /**
     * 日常活動記録を更新
     */
    public function update(Request $request, DailyRecord $record): JsonResponse
    {
        // 権限チェック
        $user = $request->user();
        if ($user->classroom_id) {
            $staffClassroom = $record->staff->classroom_id ?? null;
            if (!in_array($staffClassroom, $user->switchableClassroomIds(), true)) {
                return response()->json(['success' => false, 'message' => 'この活動を更新する権限がありません。'], 403);
            }
        }

        $validated = $request->validate([
            'activity_name'     => 'sometimes|required|string|max:255',
            'common_activity'   => 'sometimes|required|string',
            'students'          => 'nullable|array',
            'students.*.id'                        => 'required|exists:students,id',
            'students.*.health_life'               => 'nullable|string',
            'students.*.motor_sensory'             => 'nullable|string',
            'students.*.cognitive_behavior'        => 'nullable|string',
            'students.*.language_communication'    => 'nullable|string',
            'students.*.social_relations'          => 'nullable|string',
            'students.*.notes'                     => 'nullable|string',
            // Legacy domain1/domain2 fields
            'students.*.daily_note'                => 'nullable|string',
            'students.*.domain1'                   => 'nullable|string',
            'students.*.domain1_content'           => 'nullable|string',
            'students.*.domain2'                   => 'nullable|string',
            'students.*.domain2_content'           => 'nullable|string',
        ]);

        DB::transaction(function () use ($record, $validated) {
            $record->update(collect($validated)->except('students')->toArray());

            if (isset($validated['students'])) {
                $record->studentRecords()->delete();

                foreach ($validated['students'] as $studentData) {
                    // Map legacy fields if present
                    $studentData = $this->mapLegacyDomainFields($studentData);

                    StudentRecord::create([
                        'daily_record_id'          => $record->id,
                        'student_id'               => $studentData['id'],
                        'health_life'              => $studentData['health_life'] ?? null,
                        'motor_sensory'            => $studentData['motor_sensory'] ?? null,
                        'cognitive_behavior'       => $studentData['cognitive_behavior'] ?? null,
                        'language_communication'   => $studentData['language_communication'] ?? null,
                        'social_relations'         => $studentData['social_relations'] ?? null,
                        'notes'                    => $studentData['notes'] ?? null,
                    ]);
                }
            }
        });

        return response()->json([
            'success' => true,
            'data'    => $record->fresh('studentRecords'),
            'message' => '活動を更新しました。',
        ]);
    }

    /**
     * 特定の活動の生徒記録一覧を取得
     */
    public function studentRecords(Request $request, DailyRecord $record): JsonResponse
    {
        $records = $record->studentRecords()
            ->with('student:id,student_name')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $records,
        ]);
    }

    /**
     * 個別生徒の記録を保存・更新
     */
    public function storeStudentRecords(Request $request, DailyRecord $record): JsonResponse
    {
        $validated = $request->validate([
            'student_id'               => 'required|exists:students,id',
            'health_life'              => 'nullable|string',
            'motor_sensory'            => 'nullable|string',
            'cognitive_behavior'       => 'nullable|string',
            'language_communication'   => 'nullable|string',
            'social_relations'         => 'nullable|string',
            'notes'                    => 'nullable|string',
        ]);

        $studentRecord = StudentRecord::updateOrCreate(
            [
                'daily_record_id' => $record->id,
                'student_id'      => $validated['student_id'],
            ],
            [
                'health_life'              => $validated['health_life'] ?? null,
                'motor_sensory'            => $validated['motor_sensory'] ?? null,
                'cognitive_behavior'       => $validated['cognitive_behavior'] ?? null,
                'language_communication'   => $validated['language_communication'] ?? null,
                'social_relations'         => $validated['social_relations'] ?? null,
                'notes'                    => $validated['notes'] ?? null,
            ]
        );

        return response()->json([
            'success' => true,
            'data'    => $studentRecord,
        ]);
    }

    /**
     * 個別生徒の記録を削除（関連する統合ノートも削除）
     */
    public function destroyStudentRecord(Request $request, DailyRecord $record, int $studentId): JsonResponse
    {
        $user = $request->user();
        if ($user->classroom_id) {
            $staffClassroom = $record->staff->classroom_id ?? null;
            if (!in_array($staffClassroom, $user->switchableClassroomIds(), true)) {
                return response()->json(['success' => false, 'message' => 'この記録を削除する権限がありません。'], 403);
            }
        }

        $studentRecord = $record->studentRecords()->where('student_id', $studentId)->first();
        if (!$studentRecord) {
            return response()->json(['success' => false, 'message' => '生徒記録が見つかりません。'], 404);
        }

        DB::transaction(function () use ($record, $studentId, $studentRecord) {
            // 関連する統合ノート（送信履歴含む）を削除
            $record->integratedNotes()->where('student_id', $studentId)->delete();
            $studentRecord->delete();
        });

        return response()->json([
            'success' => true,
            'message' => '生徒記録を削除しました。',
        ]);
    }

    /**
     * 活動記録を削除
     */
    public function destroy(Request $request, DailyRecord $record): JsonResponse
    {
        $user = $request->user();
        if ($user->classroom_id) {
            $staffClassroom = $record->staff->classroom_id ?? null;
            if (!in_array($staffClassroom, $user->switchableClassroomIds(), true)) {
                return response()->json(['success' => false, 'message' => 'この活動を削除する権限がありません。'], 403);
            }
        }

        DB::transaction(function () use ($record) {
            $record->studentRecords()->delete();
            $record->integratedNotes()->delete();
            $record->delete();
        });

        return response()->json([
            'success' => true,
            'message' => '活動を削除しました。',
        ]);
    }

    /**
     * 統合内容の途中保存（下書き保存）
     * Legacy: save_draft_integration.php
     */
    public function saveDraft(Request $request, DailyRecord $record): JsonResponse
    {
        $user = $request->user();
        if ($user->classroom_id) {
            $staffClassroom = $record->staff->classroom_id ?? null;
            if (!in_array($staffClassroom, $user->switchableClassroomIds(), true)) {
                return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
            }
        }

        $validated = $request->validate([
            'notes'              => 'required|array',
            'notes.*.student_id' => 'required|exists:students,id',
            'notes.*.content'    => 'required|string',
        ]);

        $savedCount = 0;

        DB::transaction(function () use ($record, $validated, &$savedCount) {
            foreach ($validated['notes'] as $noteData) {
                $studentId = $noteData['student_id'];
                $content = trim($noteData['content']);

                if (empty($content)) {
                    continue;
                }

                // Skip already sent notes
                $existing = IntegratedNote::where('daily_record_id', $record->id)
                    ->where('student_id', $studentId)
                    ->first();

                if ($existing && $existing->is_sent) {
                    continue;
                }

                IntegratedNote::updateOrCreate(
                    ['daily_record_id' => $record->id, 'student_id' => $studentId],
                    ['integrated_content' => $content, 'is_sent' => false]
                );
                $savedCount++;
            }
        });

        return response()->json([
            'success' => true,
            'message' => "{$savedCount}件の統合内容を途中保存しました。",
            'saved_count' => $savedCount,
        ]);
    }

    /**
     * 統合内容を再生成（未送信分を削除してリセット）
     * Legacy: regenerate_integration.php
     */
    public function regenerateIntegrated(Request $request, DailyRecord $record): JsonResponse
    {
        $user = $request->user();
        if ($user->classroom_id) {
            $staffClassroom = $record->staff->classroom_id ?? null;
            if (!in_array($staffClassroom, $user->switchableClassroomIds(), true)) {
                return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
            }
        }

        // Delete unsent integrated notes only
        IntegratedNote::where('daily_record_id', $record->id)
            ->where('is_sent', false)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => '未送信の統合内容を削除しました。新しく統合を開始できます。',
        ]);
    }

    /**
     * 送信済み統合内容の閲覧（保護者確認状況含む）
     * Legacy: view_integrated.php
     */
    public function viewIntegrated(Request $request, DailyRecord $record): JsonResponse
    {
        $user = $request->user();
        if ($user->classroom_id) {
            $staffClassroom = $record->staff->classroom_id ?? null;
            if (!in_array($staffClassroom, $user->switchableClassroomIds(), true)) {
                return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
            }
        }

        $notes = IntegratedNote::where('daily_record_id', $record->id)
            ->with(['student:id,student_name,grade_level', 'photos'])
            ->orderBy('guardian_confirmed')
            ->get();

        $totalCount = $notes->count();
        $sentCount = $notes->where('is_sent', true)->count();
        $confirmedCount = $notes->where('guardian_confirmed', true)->count();
        $unconfirmedCount = $sentCount - $confirmedCount;

        return response()->json([
            'success' => true,
            'data' => [
                'activity' => [
                    'id' => $record->id,
                    'activity_name' => $record->activity_name,
                    'common_activity' => $record->common_activity,
                    'record_date' => $record->record_date->format('Y-m-d'),
                    'staff_name' => $record->staff->full_name ?? null,
                    'staff_id' => $record->staff_id,
                ],
                'notes' => $notes,
                'summary' => [
                    'total' => $totalCount,
                    'sent' => $sentCount,
                    'confirmed' => $confirmedCount,
                    'unconfirmed' => $unconfirmedCount,
                ],
            ],
        ]);
    }

    /**
     * 保護者への一括送信
     */
    public function sendToGuardians(Request $request, DailyRecord $record): JsonResponse
    {
        $user = $request->user();
        if ($user->classroom_id) {
            $staffClassroom = $record->staff->classroom_id ?? null;
            if (!in_array($staffClassroom, $user->switchableClassroomIds(), true)) {
                return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
            }
        }

        $validated = $request->validate([
            'notes'               => 'required|array|min:1',
            'notes.*.student_id'  => 'required|exists:students,id',
            'notes.*.content'     => 'required|string',
            'notes.*.photo_ids'   => 'nullable|array',
            'notes.*.photo_ids.*' => 'integer|exists:classroom_photos,id',
        ]);

        $sentCount = 0;

        DB::transaction(function () use ($record, $validated, &$sentCount) {
            foreach ($validated['notes'] as $noteData) {
                $studentId = $noteData['student_id'];
                $content = trim($noteData['content']);

                if (empty($content)) {
                    continue;
                }

                $student = \App\Models\Student::find($studentId);
                if (!$student) {
                    continue;
                }

                $existing = IntegratedNote::where('daily_record_id', $record->id)
                    ->where('student_id', $studentId)
                    ->first();

                if ($existing) {
                    if ($existing->is_sent) {
                        continue;
                    }
                    $existing->update([
                        'integrated_content' => $content,
                        'is_sent'            => true,
                        'sent_at'            => now(),
                    ]);
                    $integratedNoteId = $existing->id;
                    $integratedNote = $existing;
                } else {
                    $note = IntegratedNote::create([
                        'daily_record_id'    => $record->id,
                        'student_id'         => $studentId,
                        'integrated_content' => $content,
                        'is_sent'            => true,
                        'sent_at'            => now(),
                    ]);
                    $integratedNoteId = $note->id;
                    $integratedNote = $note;
                }

                // 添付写真を連絡帳にリンク (参照のみ)
                if (!empty($noteData['photo_ids'])) {
                    // 指定された写真が同じ事業所のものか確認して attach
                    $validPhotoIds = \App\Models\ClassroomPhoto::whereIn('id', $noteData['photo_ids'])
                        ->where('classroom_id', $record->classroom_id)
                        ->pluck('id')
                        ->all();
                    if (!empty($validPhotoIds)) {
                        $syncData = [];
                        foreach (array_values($validPhotoIds) as $idx => $pid) {
                            $syncData[$pid] = ['sort_order' => $idx];
                        }
                        $integratedNote->photos()->sync($syncData);
                    }
                }

                if ($student->guardian_id) {
                    SendHistory::create([
                        'integrated_note_id' => $integratedNoteId,
                        'guardian_id'        => $student->guardian_id,
                    ]);
                }

                $sentCount++;
            }
        });

        // 保護者に通知を送信
        try {
            $notificationService = app(NotificationService::class);
            $frontendUrl = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/');
            $dateStr = $record->record_date->format('n月j日');

            // 送信対象の生徒IDから保護者を取得（重複除去）
            $sentStudentIds = collect($validated['notes'])->pluck('student_id')->unique();
            $guardianIds = \App\Models\Student::whereIn('id', $sentStudentIds)
                ->whereNotNull('guardian_id')
                ->pluck('guardian_id')
                ->unique();

            $guardians = User::whereIn('id', $guardianIds)
                ->where('is_active', true)
                ->get();

            foreach ($guardians as $guardian) {
                $notificationService->notify(
                    $guardian,
                    'renrakucho',
                    '連絡帳が届きました',
                    "{$dateStr}の連絡帳が送信されました。",
                    ['url' => "{$frontendUrl}/guardian/notes"]
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Renrakucho notification error: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => "{$sentCount}件の連絡帳を保護者に送信しました。",
            'sent_count' => $sentCount,
        ]);
    }

    /**
     * AI統合文生成（ドメイン観察からまとめ文を生成）
     * Legacy: integrate_activity.php + chatgpt.php generateIntegratedNote()
     */
    public function generateIntegrated(Request $request, DailyRecord $record): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
        ]);

        $studentRecord = StudentRecord::where('daily_record_id', $record->id)
            ->where('student_id', $validated['student_id'])
            ->first();

        if (!$studentRecord) {
            return response()->json(['success' => false, 'message' => 'この生徒の記録が見つかりません。'], 404);
        }

        // Collect domain observations
        $domains = [];
        if ($studentRecord->health_life) {
            $domains[] = "【健康・生活】{$studentRecord->health_life}";
        }
        if ($studentRecord->motor_sensory) {
            $domains[] = "【運動・感覚】{$studentRecord->motor_sensory}";
        }
        if ($studentRecord->cognitive_behavior) {
            $domains[] = "【認知・行動】{$studentRecord->cognitive_behavior}";
        }
        if ($studentRecord->language_communication) {
            $domains[] = "【言語・コミュニケーション】{$studentRecord->language_communication}";
        }
        if ($studentRecord->social_relations) {
            $domains[] = "【人間関係・社会性】{$studentRecord->social_relations}";
        }

        if (empty($domains)) {
            return response()->json(['success' => false, 'message' => '領域の記録がありません。'], 422);
        }

        $student = \App\Models\Student::find($validated['student_id']);
        $domainText = implode("\n", $domains);
        $activityName = $record->activity_name;
        $commonActivity = $record->common_activity ?? '';
        $notes = $studentRecord->notes ?? '';

        // Build prompt matching legacy style (chatgpt.php generateIntegratedNote)
        $prompt = "あなたは個別支援教育の専門家です。以下の情報を元に、保護者に送る連絡帳として自然で読みやすい1つの文章にまとめてください。\n\n";

        // Support plan info (matching legacy)
        if ($record->support_plan_id) {
            $supportPlan = DB::table('activity_support_plans')->find($record->support_plan_id);
            if ($supportPlan) {
                $prompt .= "【支援案（事前計画）】\n";
                if (!empty($supportPlan->activity_purpose)) {
                    $prompt .= "・活動の目的: {$supportPlan->activity_purpose}\n";
                }
                if (!empty($supportPlan->activity_content)) {
                    $prompt .= "・活動の計画内容: {$supportPlan->activity_content}\n";
                }
                if (!empty($supportPlan->five_domains_consideration)) {
                    $prompt .= "・五領域への配慮: {$supportPlan->five_domains_consideration}\n";
                }
                if (!empty($supportPlan->other_notes)) {
                    $prompt .= "・その他: {$supportPlan->other_notes}\n";
                }
                $prompt .= "\n";
            }
        }

        $prompt .= "【活動名】\n{$activityName}\n\n";
        $prompt .= "【本日の活動内容】\n{$commonActivity}\n\n";

        if (!empty($notes)) {
            $prompt .= "【本日の様子】\n{$notes}\n\n";
        }

        $prompt .= "【気になったこと】\n{$domainText}\n\n";

        $prompt .= "上記の情報を、保護者が読みやすいように、敬体（です・ます調）で1つの自然な文章にまとめてください。";
        if ($record->support_plan_id) {
            $prompt .= "支援案の目的や配慮事項を踏まえつつ、実際の活動の様子を中心に記述してください。";
        }
        $prompt .= "箇条書きではなく、文章として流れるように記述してください。\n\n";
        $prompt .= "【重要な指示】\n";
        $prompt .= "・ポジティブで前向きな表現を使用してください。\n";
        $prompt .= "・「しかし」「ですが」「気になった点」などのネガティブな接続詞や表現は避けてください。\n";
        $prompt .= "・課題や改善点は「次のステップとして」「さらに成長するために」「これから挑戦できること」など、成長の機会として前向きに表現してください。\n";
        $prompt .= "・子どもの頑張りや成長、良かった点を中心に記述してください。\n";
        $prompt .= "・保護者が読んで嬉しくなるような、温かく励みになる文章にしてください。";

        try {
            $apiKey = config('services.openai.api_key', env('OPENAI_API_KEY'));
            if (empty($apiKey)) {
                return response()->json(['success' => false, 'message' => 'OpenAI APIキーが未設定です。'], 422);
            }

            $client = \OpenAI::client($apiKey);
            $response = $client->chat()->create([
                'model'    => 'gpt-5.4-mini-2026-03-17',
                'messages' => [
                    ['role' => 'system', 'content' => 'あなたは個別支援教育の経験豊富な教員です。保護者に向けて温かく丁寧で、前向きでポジティブな連絡帳を書きます。子どもの良い面や成長を見つけ、課題も成長の機会として前向きに伝えます。「しかし」「ですが」などのネガティブな接続詞は使わず、常にポジティブな表現を心がけます。'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.7,
                'max_completion_tokens' => 1000,
            ]);

            $content = $response->choices[0]->message->content ?? '';

            if (empty($content)) {
                throw new \Exception('AI応答が空です');
            }

            // プロンプトで与えた 5 領域ラベル（【健康・生活】等）が AI 出力に残ることがあるため除去
            $content = $this->stripDomainLabels($content);

            // Save draft integrated note
            IntegratedNote::updateOrCreate(
                ['daily_record_id' => $record->id, 'student_id' => $validated['student_id']],
                ['integrated_content' => $content, 'is_sent' => false]
            );

            // ヒヤリハット検出: 統合文と元の観察記録を追加プロンプトで分析し、
            // ヒヤリハットに値する内容があれば構造化された候補データを返す
            $hiyariCandidate = $this->detectHiyariHattoCandidate($client, $student, $record, $domainText, $notes, $content);

            return response()->json([
                'success' => true,
                'data'    => [
                    'content' => $content,
                    'hiyari_hatto_candidate' => $hiyariCandidate,
                ],
                'message' => 'AIが連絡帳文を生成しました。',
            ]);
        } catch (\Exception $e) {
            \Log::error("Generate integrated note error: " . $e->getMessage());

            // Fallback: simply combine domain observations (ラベル除去)
            $integrated = "本日は「{$activityName}」の活動を行いました。\n\n" . $this->stripDomainLabels(implode("\n", $domains));
            if ($notes) {
                $integrated .= "\n\n{$notes}";
            }

            IntegratedNote::updateOrCreate(
                ['daily_record_id' => $record->id, 'student_id' => $validated['student_id']],
                ['integrated_content' => $integrated, 'is_sent' => false]
            );

            return response()->json([
                'success' => true,
                'data'    => [
                    'content' => $integrated,
                    'hiyari_hatto_candidate' => null,
                ],
                'message' => '観察記録をまとめました（AI接続エラーのため簡易統合）。',
            ]);
        }
    }

    /**
     * 統合文に残ってしまった 5 領域ラベル（【健康・生活】等）を除去する。
     * 5 領域以外の正当な【】（例:【気になった点】など）は温存するため、
     * 固定ラベル名のホワイトリスト一致のみ除去。
     */
    private function stripDomainLabels(string $text): string
    {
        $labels = ['健康・生活', '運動・感覚', '認知・行動', '言語・コミュニケーション', '人間関係・社会性'];
        $pattern = '/【(' . implode('|', array_map('preg_quote', $labels)) . ')】/u';
        return preg_replace($pattern, '', $text);
    }

    /**
     * 連絡帳の日付と児童名で一致する写真を自動で提案する。
     *
     * URL: GET /api/staff/renrakucho/{record}/photos/suggest?student_id=X
     * 条件:
     *  - classroom_photos.classroom_id = record.classroom_id
     *  - classroom_photos.activity_date = record.record_date
     *  - classroom_photo_student に student_id が含まれる
     *
     * スタッフはこの結果を連絡帳送信画面に自動で載せ、不要な写真を
     * X ボタンで外してから送信する想定。
     */
    public function suggestPhotos(Request $request, DailyRecord $record): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|integer|exists:students,id',
        ]);

        $photos = \App\Models\ClassroomPhoto::with(['students:id,student_name'])
            ->where('classroom_id', $record->classroom_id)
            ->whereDate('activity_date', $record->record_date)
            ->whereHas('students', fn ($q) => $q->where('students.id', $validated['student_id']))
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $photos,
        ]);
    }

    /**
     * 観察記録と統合文からヒヤリハットに値する内容を AI で判定し、
     * 候補データを返す。判定不能 / 該当なし / API エラーなら null。
     *
     * 返り値構造:
     *  [
     *    'detected' => bool,
     *    'summary' => string,           // 検出された状況の要約
     *    'severity' => 'low|medium|high',
     *    'category' => string,
     *    'situation' => string,          // 発生状況の詳細
     *    'immediate_response' => string, // 即時対応の候補
     *    'prevention_measures' => string,// 再発防止の候補
     *    'reason' => string,             // なぜヒヤリハットと判定したか
     *  ]
     */
    private function detectHiyariHattoCandidate(
        $client,
        ?\App\Models\Student $student,
        DailyRecord $record,
        string $domainText,
        string $notes,
        string $integratedContent,
    ): ?array {
        try {
            $studentName = $student?->student_name ?? '';
            $activityName = $record->activity_name ?? '';

            $detectPrompt = "あなたは放課後等デイサービスの児童安全管理の専門家です。\n"
                . "以下の連絡帳の記録を読み、ヒヤリハット（危険事象・事故未遂・軽微な怪我など、"
                . "今後の事故防止のために記録すべき事象）が含まれているかどうかを判定してください。\n\n"
                . "【児童】{$studentName}\n"
                . "【活動】{$activityName}\n\n"
                . "【観察記録】\n{$domainText}\n\n"
                . (!empty($notes) ? "【メモ】\n{$notes}\n\n" : '')
                . "【統合文】\n{$integratedContent}\n\n"
                . "判定は以下の JSON 形式のみで応答してください（他の文字は出さないこと）:\n"
                . "{\n"
                . "  \"detected\": true|false,\n"
                . "  \"reason\": \"検出/非検出の理由 (50文字以内)\",\n"
                . "  \"severity\": \"low\"|\"medium\"|\"high\",\n"
                . "  \"category\": \"fall|collision|choking|ingestion|allergy|missing|conflict|self_harm|vehicle|medication|other\",\n"
                . "  \"situation\": \"発生状況の詳細 (200文字以内)\",\n"
                . "  \"immediate_response\": \"推奨される即時対応 (100文字以内)\",\n"
                . "  \"prevention_measures\": \"推奨される再発防止策 (100文字以内)\"\n"
                . "}\n\n"
                . "判定基準:\n"
                . "- 転倒/衝突/誤食/行方不明/アレルギー/喧嘩/自傷/送迎トラブル/投薬ミス 等の事象を検出\n"
                . "- 明確に事故を示唆する記述がない場合は detected=false を返す\n"
                . "- 通常の活動・成長記録は detected=false\n"
                . "- 少しでも不安・危険・事故リスクを示唆する記述があれば detected=true";

            $response = $client->chat()->create([
                'model' => 'gpt-5.4-mini-2026-03-17',
                'messages' => [
                    ['role' => 'system', 'content' => 'あなたは児童安全管理の専門家です。厳密な JSON のみで応答します。'],
                    ['role' => 'user', 'content' => $detectPrompt],
                ],
                'temperature' => 0.2,
                'max_completion_tokens' => 500,
            ]);

            $raw = $response->choices[0]->message->content ?? '';
            // Markdown コードブロックの除去
            $raw = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($raw));
            $parsed = json_decode($raw, true);

            if (!is_array($parsed) || !array_key_exists('detected', $parsed) || !$parsed['detected']) {
                return null;
            }

            return [
                'detected' => true,
                'reason' => $parsed['reason'] ?? '',
                'severity' => in_array($parsed['severity'] ?? '', ['low', 'medium', 'high'], true)
                    ? $parsed['severity']
                    : 'low',
                'category' => (string) ($parsed['category'] ?? 'other'),
                'situation' => (string) ($parsed['situation'] ?? ''),
                'immediate_response' => (string) ($parsed['immediate_response'] ?? ''),
                'prevention_measures' => (string) ($parsed['prevention_measures'] ?? ''),
            ];
        } catch (\Throwable $e) {
            \Log::warning('Hiyari hatto detection failed: ' . $e->getMessage());
            return null;
        }
    }
}
