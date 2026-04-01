<?php

namespace App\Console\Commands;

use App\Models\ChatMessage;
use App\Models\ChatMessageStaffRead;
use App\Models\StudentChatMessage;
use App\Models\StaffChatMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DeleteOldChatMessages extends Command
{
    protected $signature = 'chat:delete-old {--months=6 : 削除対象の経過月数} {--dry-run : 実際には削除せず件数のみ表示}';

    protected $description = '指定月数（デフォルト6ヶ月）経過した未アーカイブのチャットメッセージと添付ファイルを削除';

    public function handle(): int
    {
        $months = (int) $this->option('months');
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subMonths($months);

        $this->info("対象: {$cutoff->toDateString()} 以前の未アーカイブメッセージ" . ($dryRun ? ' [DRY RUN]' : ''));

        $stats = [
            'chat_messages' => $this->deleteFromTable('chat_messages', $cutoff, $dryRun),
            'student_chat_messages' => $this->deleteFromTable('student_chat_messages', $cutoff, $dryRun),
            'staff_chat_messages' => $this->deleteFromTable('staff_chat_messages', $cutoff, $dryRun),
        ];

        foreach ($stats as $table => $counts) {
            $this->info("{$table}: メッセージ {$counts['messages']}件削除, 添付ファイル {$counts['files']}件削除");
        }

        $total = array_sum(array_column($stats, 'messages'));
        Log::info("DeleteOldChatMessages: {$total}件のメッセージを削除" . ($dryRun ? ' (dry-run)' : ''), $stats);

        return self::SUCCESS;
    }

    private function deleteFromTable(string $table, \Carbon\Carbon $cutoff, bool $dryRun): array
    {
        $query = DB::table($table)
            ->where('created_at', '<', $cutoff)
            ->where('is_archived', false);

        $count = $query->count();

        // 添付ファイルのパスを収集
        $attachmentColumn = $table === 'student_chat_messages' ? 'attachment_path' : 'attachment_path';
        $attachments = (clone $query)->whereNotNull($attachmentColumn)->pluck($attachmentColumn);
        $fileCount = 0;

        if (!$dryRun && $count > 0) {
            // 添付ファイルを削除
            foreach ($attachments as $path) {
                if ($path && Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                    $fileCount++;
                }
            }

            // chat_messagesの場合はstaff_readsも削除
            if ($table === 'chat_messages') {
                $messageIds = (clone $query)->pluck('id');
                ChatMessageStaffRead::whereIn('message_id', $messageIds)->delete();
            }

            // メッセージを物理削除
            DB::table($table)
                ->where('created_at', '<', $cutoff)
                ->where('is_archived', false)
                ->delete();
        }

        return ['messages' => $count, 'files' => $dryRun ? $attachments->count() : $fileCount];
    }
}
