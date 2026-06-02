<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Jobs\RunAiGenerationTaskJob;
use App\Models\AiGenerationTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 非同期 AI 生成タスクの受付・状態取得。
 *
 * 外出先のモバイル回線で長時間 (~50秒) の同期リクエストが切れて
 * 「支援案を書き出せない」問題への対策。生成はキューで実行し、
 * フロントは task_id をポーリングして結果を受け取る。
 */
class AiGenerationTaskController extends Controller
{
    /** 受付可能な生成種別と入力バリデーション */
    private const TYPES = [
        'activity_five_domains' => [
            'activity_name'    => 'required|string',
            'activity_purpose' => 'nullable|string',
            'activity_content' => 'nullable|string',
            'target_grade'     => 'nullable|string',
        ],
        'activity_schedule_content' => [
            'activity_name'    => 'required|string',
            'activity_purpose' => 'nullable|string',
            'total_duration'   => 'required|integer',
            'schedule'         => 'required|array',
            'target_grade'     => 'nullable|string',
        ],
    ];

    /**
     * 生成タスクを受け付けてキューに載せる。
     */
    public function store(Request $request): JsonResponse
    {
        $type = (string) $request->input('type');
        if (! isset(self::TYPES[$type])) {
            return response()->json(['success' => false, 'message' => '未対応の生成種別です。'], 422);
        }

        $validated = $request->validate(self::TYPES[$type]);

        $task = AiGenerationTask::create([
            'user_id' => $request->user()->id,
            'type'    => $type,
            'status'  => 'pending',
            'input'   => $validated,
        ]);

        RunAiGenerationTaskJob::dispatch($task->id);

        return response()->json([
            'success' => true,
            'data'    => ['task_id' => $task->id, 'status' => $task->status],
        ], 202);
    }

    /**
     * タスクの状態・結果を取得 (ポーリング用)。
     * 自分のタスクのみ参照可。
     */
    public function show(Request $request, AiGenerationTask $task): JsonResponse
    {
        if ($task->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'task_id' => $task->id,
                'status'  => $task->status,
                'result'  => $task->status === 'completed' ? $task->result : null,
                'error'   => $task->status === 'failed' ? ($task->error ?: '生成に失敗しました') : null,
            ],
        ]);
    }
}
