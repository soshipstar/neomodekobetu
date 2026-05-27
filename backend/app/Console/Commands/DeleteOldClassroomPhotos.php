<?php

namespace App\Console\Commands;

use App\Models\ClassroomPhoto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * 事業所写真ライブラリの古い写真を自動削除する。
 *
 * ユーザー要望 (写真容量管理):
 *   100MB 上限のままだと、てらこやプラスは ~30 日、narZE は ~80 日で
 *   上限に達してアップロードが止まる。古い写真を自動でクリーンアップする
 *   仕組みを入れる。
 *
 * 削除条件:
 *   - created_at が cutoff より古い
 *   - かつ「連絡帳に添付されていない」(integrated_note_photos pivot に
 *     登場しない) 写真のみ削除。
 *     → 連絡帳に紐づいた写真は保護者の閲覧履歴・施設の証跡として残す。
 *
 * 物理ファイルもストレージから削除する (FK CASCADE で
 *  classroom_photo_student / integrated_note_photos は連動)。
 *
 * 使い方:
 *   php artisan photos:cleanup-old                 # デフォルト 90 日
 *   php artisan photos:cleanup-old --days=180
 *   php artisan photos:cleanup-old --dry-run       # 件数のみ表示、実削除なし
 *   php artisan photos:cleanup-old --classroom=10  # 特定事業所のみ対象
 */
class DeleteOldClassroomPhotos extends Command
{
    protected $signature = 'photos:cleanup-old
        {--days=90 : この日数より古い写真を削除対象にする}
        {--classroom= : 特定事業所のみ対象 (省略時は全事業所)}
        {--dry-run : 実際には削除せず件数のみ表示}';

    protected $description = '事業所写真ライブラリの古い写真を自動削除 (連絡帳に添付済みの写真は除外)';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = (bool) $this->option('dry-run');
        $classroomFilter = $this->option('classroom');
        $cutoff = now()->subDays($days);

        $this->info("対象: {$cutoff->toDateString()} ({$days} 日前) より古い写真" . ($dryRun ? ' [DRY RUN]' : ''));
        if ($classroomFilter) {
            $this->info("事業所フィルタ: classroom_id = {$classroomFilter}");
        }

        // 連絡帳に添付されている写真 ID を除外する subquery
        $base = ClassroomPhoto::query()
            ->where('created_at', '<', $cutoff)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('integrated_note_photos')
                  ->whereColumn('integrated_note_photos.classroom_photo_id', 'classroom_photos.id');
            });

        if ($classroomFilter) {
            $base->where('classroom_id', (int) $classroomFilter);
        }

        $totalCount = (clone $base)->count();
        if ($totalCount === 0) {
            $this->info('対象写真はありません。');
            return self::SUCCESS;
        }

        $this->info("削除対象: {$totalCount} 件");

        // 事業所別件数も出しておく (運用ログ用)
        $perClassroom = (clone $base)
            ->selectRaw('classroom_id, COUNT(*) AS cnt, COALESCE(SUM(file_size), 0) AS bytes')
            ->groupBy('classroom_id')
            ->orderByDesc('cnt')
            ->get();
        foreach ($perClassroom as $row) {
            $mb = round(((int) $row->bytes) / 1024 / 1024, 2);
            $this->info("  classroom_id={$row->classroom_id}: {$row->cnt} 件 / {$mb} MB");
        }

        if ($dryRun) {
            $this->info('[DRY RUN] 実削除は行いませんでした。');
            return self::SUCCESS;
        }

        // chunk で実行 (メモリ安全)。各写真ごとに物理ファイル削除 → DB レコード削除。
        // FK CASCADE で classroom_photo_student も自動削除される
        // (integrated_note_photos は subquery で除外済みなので非空配列は無いはず)。
        $deletedCount = 0;
        $deletedBytes = 0;
        $fileDeleteFailures = 0;

        (clone $base)
            ->select(['id', 'file_path', 'file_size'])
            ->chunkById(200, function ($photos) use (&$deletedCount, &$deletedBytes, &$fileDeleteFailures) {
                foreach ($photos as $photo) {
                    try {
                        if ($photo->file_path && Storage::disk('public')->exists($photo->file_path)) {
                            Storage::disk('public')->delete($photo->file_path);
                        }
                    } catch (\Throwable $e) {
                        $fileDeleteFailures++;
                        Log::warning("photos:cleanup-old file delete failed", [
                            'photo_id'  => $photo->id,
                            'file_path' => $photo->file_path,
                            'error'     => $e->getMessage(),
                        ]);
                    }
                    $photo->delete();
                    $deletedCount++;
                    $deletedBytes += (int) ($photo->file_size ?? 0);
                }
            });

        $freedMb = round($deletedBytes / 1024 / 1024, 2);
        $this->info("完了: {$deletedCount} 件削除 / 空き容量 {$freedMb} MB");
        if ($fileDeleteFailures > 0) {
            $this->warn("物理ファイル削除に失敗: {$fileDeleteFailures} 件 (Laravel ログ参照)");
        }

        Log::info('photos:cleanup-old completed', [
            'days'           => $days,
            'classroom'      => $classroomFilter,
            'deleted_count'  => $deletedCount,
            'deleted_bytes'  => $deletedBytes,
            'file_delete_failures' => $fileDeleteFailures,
        ]);

        return self::SUCCESS;
    }
}
