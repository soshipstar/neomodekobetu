<?php

namespace App\Jobs;

use App\Models\AiGenerationTask;
use App\Services\ActivitySupportPlanAiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * 非同期 AI 生成タスクを実行するジョブ。
 *
 * 外出先のモバイル回線で ~50 秒の同期リクエストが切断され「支援案を書き出せない」
 * 問題への対策。生成をキューで実行し結果を DB に保存、フロントはポーリングで取得する。
 *
 * タイムアウトは OpenAI 呼び出し (~55秒) に余裕を持たせて 180 秒。
 */
class RunAiGenerationTaskJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $backoff = 10;
    public int $timeout = 180;

    public function __construct(private readonly int $taskId)
    {
        // queue worker は redis の default と ai キューを処理する設定にする。
        $this->onQueue('ai');
    }

    public function handle(ActivitySupportPlanAiService $activityAi): void
    {
        $task = AiGenerationTask::find($this->taskId);
        if (! $task || $task->status === 'completed') {
            return;
        }

        $task->update(['status' => 'processing', 'started_at' => now()]);

        try {
            $result = match ($task->type) {
                'activity_five_domains'    => $activityAi->generateFiveDomains($task->input, $task->user_id),
                'activity_schedule_content' => $activityAi->generateScheduleContent($task->input, $task->user_id),
                default => throw new \InvalidArgumentException("未知の生成種別: {$task->type}"),
            };

            $task->update([
                'status'      => 'completed',
                'result'      => $result,
                'finished_at' => now(),
                'duration_ms' => $task->started_at ? (int) ($task->started_at->diffInMilliseconds(now())) : null,
                'error'       => null,
            ]);
        } catch (\Throwable $e) {
            Log::error('RunAiGenerationTaskJob failed', ['task_id' => $this->taskId, 'error' => $e->getMessage()]);
            // 最終試行で失敗ステータスを確定する (リトライ中は processing のまま)
            if ($this->attempts() >= $this->tries) {
                $task->update([
                    'status'      => 'failed',
                    'error'       => mb_substr($e->getMessage(), 0, 500),
                    'finished_at' => now(),
                ]);
            }
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $task = AiGenerationTask::find($this->taskId);
        if ($task && $task->status !== 'completed') {
            $task->update([
                'status'      => 'failed',
                'error'       => mb_substr($exception->getMessage(), 0, 500),
                'finished_at' => now(),
            ]);
        }
    }
}
