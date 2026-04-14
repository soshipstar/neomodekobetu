<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\BugReport;
use App\Models\BugReportReply;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BugReportController extends Controller
{
    /**
     * バグ報告一覧（スタッフ: 自分の報告、管理者: 全報告）
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = BugReport::with(['reporter:id,full_name,classroom_id', 'reporter.classroom:id,classroom_name'])
            ->withCount('replies');

        if ($user->is_master) {
            // マスター管理者: 全報告
        } elseif ($user->isCompanyAdmin()) {
            // 企業管理者: 自社教室のスタッフの報告
            $classroomIds = $user->switchableClassroomIds();
            $query->whereHas('reporter', fn ($q) => $q->whereIn('classroom_id', $classroomIds));
        } else {
            // 通常スタッフ: 自分の報告のみ
            $query->where('reporter_id', $user->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $reports = $query->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return response()->json(['success' => true, 'data' => $reports]);
    }

    /**
     * バグ報告を送信
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page_url'    => 'required|string|max:500',
            'description' => 'required|string|max:5000',
            'console_log' => 'nullable|string|max:10000',
            'screenshot'  => 'nullable|file|mimes:jpg,jpeg,png,webp|max:5120',
            'priority'    => 'nullable|string|in:low,normal,high,critical',
        ]);

        $screenshotPath = null;
        if ($request->hasFile('screenshot')) {
            $file = $request->file('screenshot');
            $uuid = (string) Str::uuid();
            $ext = $file->getClientOriginalExtension() ?: 'png';
            $screenshotPath = "bug_reports/{$uuid}.{$ext}";
            Storage::disk('public')->put($screenshotPath, file_get_contents($file->getRealPath()));
        }

        $report = BugReport::create([
            'reporter_id'     => $request->user()->id,
            'page_url'        => $validated['page_url'],
            'description'     => $validated['description'],
            'console_log'     => $validated['console_log'] ?? null,
            'screenshot_path' => $screenshotPath,
            'priority'        => $validated['priority'] ?? 'normal',
        ]);

        return response()->json([
            'success' => true,
            'data'    => $report->load(['reporter:id,full_name']),
            'message' => 'バグ報告を送信しました。',
        ], 201);
    }

    /**
     * 報告詳細（返信付き）
     */
    public function show(Request $request, BugReport $bugReport): JsonResponse
    {
        $this->authorizeAccess($request, $bugReport);

        $bugReport->load([
            'reporter:id,full_name,classroom_id',
            'reporter.classroom:id,classroom_name',
            'replies.user:id,full_name',
        ]);

        // スクリーンショットURL付与
        $data = $bugReport->toArray();
        if ($bugReport->screenshot_path) {
            $data['screenshot_url'] = Storage::disk('public')->url($bugReport->screenshot_path);
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * 返信を追加
     */
    public function reply(Request $request, BugReport $bugReport): JsonResponse
    {
        $this->authorizeAccess($request, $bugReport);

        $validated = $request->validate([
            'message' => 'required|string|max:5000',
        ]);

        $user = $request->user();

        $reply = BugReportReply::create([
            'bug_report_id' => $bugReport->id,
            'user_id'       => $user->id,
            'message'       => $validated['message'],
        ]);

        // 返信相手に通知（報告者以外が返信→報告者へ、報告者が返信→管理者へは不要）
        if ($bugReport->reporter_id !== $user->id) {
            try {
                $reporter = User::find($bugReport->reporter_id);
                if ($reporter) {
                    app(NotificationService::class)->notify(
                        $reporter,
                        'bug_report',
                        'バグ報告に返信がありました',
                        $user->full_name . ': ' . mb_substr($validated['message'], 0, 80),
                        ['url' => '/staff/bug-reports'],
                    );
                }
            } catch (\Throwable $e) {
                // 通知失敗は無視
            }
        }

        return response()->json([
            'success' => true,
            'data'    => $reply->load('user:id,full_name'),
            'message' => '返信しました。',
        ], 201);
    }

    /**
     * ステータス変更（管理者のみ）
     */
    public function updateStatus(Request $request, BugReport $bugReport): JsonResponse
    {
        $user = $request->user();
        if (!$user->is_master && !$user->isCompanyAdmin()) {
            return response()->json(['success' => false, 'message' => '権限がありません。'], 403);
        }

        $validated = $request->validate([
            'status' => 'required|string|in:open,in_progress,resolved,closed',
        ]);

        $oldStatus = $bugReport->status;
        $bugReport->update(['status' => $validated['status']]);

        // 報告者に通知（自分自身への変更は通知しない）
        if ($bugReport->reporter_id !== $user->id) {
            $statusLabels = ['open' => '未対応', 'in_progress' => '対応中', 'resolved' => '解決済み', 'closed' => '完了'];
            $newLabel = $statusLabels[$validated['status']] ?? $validated['status'];
            try {
                $reporter = User::find($bugReport->reporter_id);
                if ($reporter) {
                    app(NotificationService::class)->notify(
                        $reporter,
                        'bug_report',
                        'バグ報告のステータスが変更されました',
                        "「{$newLabel}」に変更されました: " . mb_substr($bugReport->description, 0, 50),
                        ['url' => '/staff/bug-reports'],
                    );
                }
            } catch (\Throwable $e) {
                // 通知失敗は無視
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'ステータスを更新しました。',
        ]);
    }

    private function authorizeAccess(Request $request, BugReport $bugReport): void
    {
        $user = $request->user();
        if ($user->is_master) return;
        if ($user->isCompanyAdmin()) return;
        if ($bugReport->reporter_id === $user->id) return;
        abort(403, 'アクセス権限がありません。');
    }
}
