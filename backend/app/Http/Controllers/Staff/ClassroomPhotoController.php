<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\ClassroomPhoto;
use App\Services\ImageCompressionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ClassroomPhotoController extends Controller
{
    /**
     * 写真一覧 (検索・フィルタ対応)
     *
     * クエリ: keyword (活動内容), from, to (活動日範囲), student_id (児童タグ)
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $accessibleIds = $user->accessibleClassroomIds();

        $query = ClassroomPhoto::with(['students:id,student_name', 'uploader:id,full_name'])
            ->whereIn('classroom_id', $accessibleIds);

        if ($request->filled('classroom_id')) {
            $query->where('classroom_id', $request->classroom_id);
        }
        if ($request->filled('keyword')) {
            $kw = '%' . $request->keyword . '%';
            $query->where('activity_description', 'like', $kw);
        }
        if ($request->filled('from')) {
            $query->whereDate('activity_date', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('activity_date', '<=', $request->to);
        }
        if ($request->filled('student_id')) {
            $query->whereHas('students', fn ($q) => $q->where('students.id', $request->student_id));
        }

        $photos = $query->orderByDesc('activity_date')
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 40));

        return response()->json([
            'success' => true,
            'data' => $photos,
        ]);
    }

    /**
     * 事業所のストレージ使用量
     */
    public function storageUsage(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroomId = $request->input('classroom_id', $user->classroom_id);
        if (!in_array((int) $classroomId, $user->switchableClassroomIds(), true)) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $used = ClassroomPhoto::classroomStorageUsed((int) $classroomId);
        $limit = ClassroomPhoto::STORAGE_LIMIT_BYTES;

        return response()->json([
            'success' => true,
            'data' => [
                'classroom_id' => (int) $classroomId,
                'used_bytes' => $used,
                'limit_bytes' => $limit,
                'used_mb' => round($used / 1024 / 1024, 2),
                'limit_mb' => round($limit / 1024 / 1024, 2),
                'available_bytes' => max(0, $limit - $used),
            ],
        ]);
    }

    /**
     * 写真アップロード (圧縮 + 容量チェック + メタデータ保存)
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'photo' => 'required|file|mimes:jpg,jpeg,png,webp,gif,heic,heif|max:25600', // 25MB (フロント側で圧縮済み、フォールバック)
            'classroom_id' => 'required|integer|exists:classrooms,id',
            'activity_description' => 'nullable|string|max:2000',
            'activity_date' => 'nullable|date',
            'grade_level' => 'nullable|string|in:preschool,elementary,junior_high,high_school',
            'activity_tag_id' => 'nullable|integer|exists:classroom_tags,id',
            'student_ids' => 'nullable|array',
            'student_ids.*' => 'integer|exists:students,id',
        ]);

        if (!in_array((int) $validated['classroom_id'], $user->switchableClassroomIds(), true)) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        // 現在の使用容量チェック (この段階ではまだ圧縮していないので保守的にチェック)
        $used = ClassroomPhoto::classroomStorageUsed((int) $validated['classroom_id']);
        if ($used >= ClassroomPhoto::STORAGE_LIMIT_BYTES) {
            return response()->json([
                'success' => false,
                'message' => '事業所の写真保存容量 (100MB) を超えています。不要な写真を削除してから再度お試しください。',
                'data' => [
                    'used_bytes' => $used,
                    'limit_bytes' => ClassroomPhoto::STORAGE_LIMIT_BYTES,
                ],
            ], 422);
        }

        $file = $request->file('photo');
        $source = $file->getRealPath();

        // 一時ファイルに圧縮
        $tmpDest = tempnam(sys_get_temp_dir(), 'compressed_') . '.jpg';
        try {
            $compressed = app(ImageCompressionService::class)->compressToTarget(
                $source,
                $tmpDest,
                ClassroomPhoto::TARGET_FILE_SIZE,
                1600,
            );
        } catch (\Throwable $e) {
            @unlink($tmpDest);
            return response()->json([
                'success' => false,
                'message' => '画像の圧縮に失敗しました: ' . $e->getMessage(),
            ], 422);
        }

        // 圧縮後のサイズで容量再チェック
        if ($used + $compressed['size'] > ClassroomPhoto::STORAGE_LIMIT_BYTES) {
            @unlink($tmpDest);
            return response()->json([
                'success' => false,
                'message' => sprintf(
                    '圧縮後の画像サイズ (%d KB) を保存すると事業所容量 100MB を超えます。使用量: %d MB / 100 MB',
                    (int) ceil($compressed['size'] / 1024),
                    (int) round($used / 1024 / 1024),
                ),
            ], 422);
        }

        // storage/app/public/classroom_photos/{classroom_id}/{uuid}.jpg に保存
        $uuid = (string) Str::uuid();
        $relPath = "classroom_photos/{$validated['classroom_id']}/{$uuid}.jpg";
        Storage::disk('public')->put($relPath, file_get_contents($tmpDest));
        @unlink($tmpDest);

        $photo = DB::transaction(function () use ($user, $validated, $compressed, $relPath) {
            // 曜日を自動推定
            $dayOfWeek = null;
            if (!empty($validated['activity_date'])) {
                $dayMapping = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
                $dayOfWeek = $dayMapping[\Carbon\Carbon::parse($validated['activity_date'])->dayOfWeek];
            }

            $photo = ClassroomPhoto::create([
                'classroom_id' => $validated['classroom_id'],
                'uploader_id' => $user->id,
                'file_path' => $relPath,
                'file_size' => $compressed['size'],
                'mime' => $compressed['mime'],
                'width' => $compressed['width'],
                'height' => $compressed['height'],
                'activity_description' => $validated['activity_description'] ?? null,
                'activity_date' => $validated['activity_date'] ?? null,
                'day_of_week' => $dayOfWeek,
                'grade_level' => $validated['grade_level'] ?? null,
                'activity_tag_id' => $validated['activity_tag_id'] ?? null,
            ]);
            if (!empty($validated['student_ids'])) {
                $photo->students()->sync($validated['student_ids']);
            }
            return $photo;
        });

        return response()->json([
            'success' => true,
            'data' => $photo->load(['students:id,student_name', 'uploader:id,full_name']),
            'message' => '写真をアップロードしました。',
        ], 201);
    }

    /**
     * 写真詳細
     */
    public function show(Request $request, ClassroomPhoto $photo): JsonResponse
    {
        $this->authorizeAccess($request, $photo);
        $photo->load(['students:id,student_name', 'uploader:id,full_name', 'classroom:id,classroom_name']);

        return response()->json([
            'success' => true,
            'data' => $photo,
        ]);
    }

    /**
     * 写真更新 (メタデータのみ)
     */
    public function update(Request $request, ClassroomPhoto $photo): JsonResponse
    {
        $this->authorizeAccess($request, $photo);

        $validated = $request->validate([
            'activity_description' => 'nullable|string|max:2000',
            'activity_date' => 'nullable|date',
            'grade_level' => 'nullable|string|in:preschool,elementary,junior_high,high_school',
            'activity_tag_id' => 'nullable|integer|exists:classroom_tags,id',
            'student_ids' => 'nullable|array',
            'student_ids.*' => 'integer|exists:students,id',
        ]);

        // 曜日を自動推定
        $updateData = collect($validated)->except('student_ids')->toArray();
        if (array_key_exists('activity_date', $updateData) && $updateData['activity_date']) {
            $dayMapping = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
            $updateData['day_of_week'] = $dayMapping[\Carbon\Carbon::parse($updateData['activity_date'])->dayOfWeek];
        }
        $photo->update($updateData);
        if (array_key_exists('student_ids', $validated)) {
            $photo->students()->sync($validated['student_ids'] ?? []);
        }

        return response()->json([
            'success' => true,
            'data' => $photo->fresh(['students:id,student_name', 'uploader:id,full_name']),
            'message' => '写真情報を更新しました。',
        ]);
    }

    /**
     * 写真削除 (ファイル + DB)
     */
    public function destroy(Request $request, ClassroomPhoto $photo): JsonResponse
    {
        $this->authorizeAccess($request, $photo);

        $path = $photo->file_path;
        DB::transaction(function () use ($photo) {
            $photo->students()->detach();
            $photo->delete();
        });
        if ($path) {
            Storage::disk('public')->delete($path);
        }

        return response()->json([
            'success' => true,
            'message' => '写真を削除しました。',
        ]);
    }

    private function authorizeAccess(Request $request, ClassroomPhoto $photo): void
    {
        $user = $request->user();
        if (!in_array($photo->classroom_id, $user->switchableClassroomIds(), true)) {
            abort(403, 'アクセス権限がありません。');
        }
    }
}
