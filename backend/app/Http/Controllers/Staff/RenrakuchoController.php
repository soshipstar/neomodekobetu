<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\DailyRecord;
use App\Models\IntegratedNote;
use App\Models\SendHistory;
use App\Models\StudentRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RenrakuchoController extends Controller
{
    /**
     * 連絡帳（日常活動記録）一覧を取得
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = DailyRecord::with(['staff:id,full_name'])
            ->withCount('studentRecords');

        // 教室フィルタ
        if ($user->classroom_id) {
            $query->whereHas('staff', function ($q) use ($user) {
                $q->where('classroom_id', $user->classroom_id);
            });
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
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'record_date'       => 'required|date',
            'activity_name'     => 'required|string|max:255',
            'common_activity'   => 'required|string',
            'students'          => 'required|array|min:1',
            'students.*.id'                        => 'required|exists:students,id',
            'students.*.health_life'               => 'nullable|string',
            'students.*.motor_sensory'             => 'nullable|string',
            'students.*.cognitive_behavior'        => 'nullable|string',
            'students.*.language_communication'    => 'nullable|string',
            'students.*.social_relations'          => 'nullable|string',
            'students.*.notes'                     => 'nullable|string',
        ]);

        $record = DB::transaction(function () use ($request, $validated) {
            $record = DailyRecord::create([
                'record_date'     => $validated['record_date'],
                'staff_id'        => $request->user()->id,
                'classroom_id'    => $request->user()->classroom_id,
                'activity_name'   => $validated['activity_name'],
                'common_activity' => $validated['common_activity'],
            ]);

            foreach ($validated['students'] as $studentData) {
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
            if ($staffClassroom !== $user->classroom_id) {
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
        ]);

        DB::transaction(function () use ($record, $validated) {
            $record->update(collect($validated)->except('students')->toArray());

            if (isset($validated['students'])) {
                $record->studentRecords()->delete();

                foreach ($validated['students'] as $studentData) {
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
     * 活動記録を削除
     */
    public function destroy(Request $request, DailyRecord $record): JsonResponse
    {
        $user = $request->user();
        if ($user->classroom_id) {
            $staffClassroom = $record->staff->classroom_id ?? null;
            if ($staffClassroom !== $user->classroom_id) {
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
     * 保護者への一括送信
     */
    public function sendToGuardians(Request $request, DailyRecord $record): JsonResponse
    {
        $user = $request->user();
        if ($user->classroom_id) {
            $staffClassroom = $record->staff->classroom_id ?? null;
            if ($staffClassroom !== $user->classroom_id) {
                return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
            }
        }

        $validated = $request->validate([
            'notes'              => 'required|array|min:1',
            'notes.*.student_id' => 'required|exists:students,id',
            'notes.*.content'    => 'required|string',
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
                } else {
                    $note = IntegratedNote::create([
                        'daily_record_id'    => $record->id,
                        'student_id'         => $studentId,
                        'integrated_content' => $content,
                        'is_sent'            => true,
                        'sent_at'            => now(),
                    ]);
                    $integratedNoteId = $note->id;
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

        return response()->json([
            'success' => true,
            'message' => "{$sentCount}件の連絡帳を保護者に送信しました。",
            'sent_count' => $sentCount,
        ]);
    }

    /**
     * AI統合文生成（ドメイン観察からまとめ文を生成）
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

        $prompt = <<<PROMPT
あなたは放課後等デイサービスの連絡帳作成アシスタントです。
以下の活動記録と5領域の観察メモから、保護者向けの連絡帳文を作成してください。

【活動名】{$activityName}
【本日の活動（共通）】{$commonActivity}
【児童名】{$student->student_name}
【個別メモ】{$notes}

【5領域の観察記録】
{$domainText}

以下の要件に従って作成してください：
- 保護者が読んで嬉しくなるような、温かみのある文章
- 具体的なエピソードを含める
- 200〜400文字程度
- 5領域の観察を自然に統合した文章にする
- 敬体（ですます調）で書く
PROMPT;

        try {
            $response = \OpenAI\Laravel\Facades\OpenAI::chat()->create([
                'model'    => config('services.openai.model', 'gpt-5'),
                'messages' => [
                    ['role' => 'system', 'content' => 'あなたは放課後等デイサービスの連絡帳作成アシスタントです。'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.7,
                'max_tokens'  => 800,
            ]);

            $content = $response->choices[0]->message->content ?? '';

            if (empty($content)) {
                throw new \Exception('AI応答が空です');
            }

            // Save draft integrated note
            IntegratedNote::updateOrCreate(
                ['daily_record_id' => $record->id, 'student_id' => $validated['student_id']],
                ['integrated_content' => $content, 'is_sent' => false]
            );

            return response()->json([
                'success' => true,
                'data'    => ['content' => $content],
                'message' => 'AIが連絡帳文を生成しました。',
            ]);
        } catch (\Exception $e) {
            \Log::error("Generate integrated note error: " . $e->getMessage());

            // Fallback: simply combine domain observations
            $integrated = "本日は「{$activityName}」の活動を行いました。\n\n" . implode("\n", $domains);
            if ($notes) {
                $integrated .= "\n\n{$notes}";
            }

            IntegratedNote::updateOrCreate(
                ['daily_record_id' => $record->id, 'student_id' => $validated['student_id']],
                ['integrated_content' => $integrated, 'is_sent' => false]
            );

            return response()->json([
                'success' => true,
                'data'    => ['content' => $integrated],
                'message' => '観察記録をまとめました（AI接続エラーのため簡易統合）。',
            ]);
        }
    }
}
