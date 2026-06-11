<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\WorkManual;
use App\Models\WorkManualStep;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * 作業マニュアル API。
 *
 * - 一覧 / 詳細 / 作成 / 更新 / 削除
 * - 画像/動画は public ディスクにアップロード (storage/app/public/work-manuals/)
 * - 利用者個別 (合理的配慮版) は student_id 付きで作成
 */
class WorkManualController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $classroomId = $request->user()->classroom_id;
        $studentId   = $request->integer('student_id');

        $query = WorkManual::query()
            ->where('classroom_id', $classroomId)
            ->where('is_published', true)
            ->with(['student:id,student_name'])
            ->orderByDesc('id');

        if ($studentId > 0) {
            // 利用者個別 + 共有手順書
            $query->where(function ($q) use ($studentId) {
                $q->whereNull('student_id')->orWhere('student_id', $studentId);
            });
        } else {
            // 共有手順書のみ
            $query->whereNull('student_id');
        }

        return response()->json(['data' => $query->limit(200)->get()]);
    }

    public function show(Request $request, WorkManual $manual): JsonResponse
    {
        $this->authorize($request, $manual);
        return response()->json([
            'data' => $manual->load(['student:id,student_name', 'steps']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'             => 'required|string|max:200',
            'category'          => 'nullable|string|max:50',
            'summary'           => 'nullable|string|max:5000',
            'difficulty'        => 'nullable|string|max:20',
            'estimated_minutes' => 'nullable|integer|min:0|max:1440',
            'student_id'        => 'nullable|integer|exists:students,id',
            'is_published'      => 'nullable|boolean',
            'steps'             => 'nullable|array',
            'steps.*.title'        => 'nullable|string|max:200',
            'steps.*.description'  => 'nullable|string|max:5000',
            'steps.*.image_path'   => 'nullable|string|max:500',
            'steps.*.video_path'   => 'nullable|string|max:500',
            'steps.*.caution'      => 'nullable|string|max:2000',
            'steps.*.checkpoint'   => 'nullable|string|max:2000',
        ]);

        $manual = DB::transaction(function () use ($request, $validated) {
            $manual = WorkManual::create([
                'classroom_id'      => $request->user()->classroom_id,
                'title'             => $validated['title'],
                'category'          => $validated['category'] ?? null,
                'summary'           => $validated['summary'] ?? null,
                'difficulty'        => $validated['difficulty'] ?? null,
                'estimated_minutes' => $validated['estimated_minutes'] ?? null,
                'student_id'        => $validated['student_id'] ?? null,
                'is_published'      => $validated['is_published'] ?? true,
                'created_by'        => $request->user()->id,
            ]);
            foreach ($validated['steps'] ?? [] as $i => $step) {
                if (empty($step['title']) && empty($step['description'])) continue;
                WorkManualStep::create(array_merge($step, [
                    'work_manual_id' => $manual->id,
                    'sort_order'     => $i,
                ]));
            }
            return $manual;
        });

        return response()->json(['data' => $manual->load(['student:id,student_name', 'steps'])], 201);
    }

    public function update(Request $request, WorkManual $manual): JsonResponse
    {
        $this->authorize($request, $manual);

        $validated = $request->validate([
            'title'             => 'sometimes|string|max:200',
            'category'          => 'sometimes|nullable|string|max:50',
            'summary'           => 'sometimes|nullable|string|max:5000',
            'difficulty'        => 'sometimes|nullable|string|max:20',
            'estimated_minutes' => 'sometimes|nullable|integer|min:0|max:1440',
            'student_id'        => 'sometimes|nullable|integer|exists:students,id',
            'is_published'      => 'sometimes|boolean',
            'steps'             => 'sometimes|array',
            'steps.*.title'        => 'nullable|string|max:200',
            'steps.*.description'  => 'nullable|string|max:5000',
            'steps.*.image_path'   => 'nullable|string|max:500',
            'steps.*.video_path'   => 'nullable|string|max:500',
            'steps.*.caution'      => 'nullable|string|max:2000',
            'steps.*.checkpoint'   => 'nullable|string|max:2000',
        ]);

        DB::transaction(function () use ($manual, $validated) {
            $manual->update(collect($validated)->except('steps')->toArray());
            if (array_key_exists('steps', $validated)) {
                $manual->steps()->delete();
                foreach ($validated['steps'] as $i => $step) {
                    if (empty($step['title']) && empty($step['description'])) continue;
                    WorkManualStep::create(array_merge($step, [
                        'work_manual_id' => $manual->id,
                        'sort_order'     => $i,
                    ]));
                }
            }
        });

        return response()->json(['data' => $manual->fresh(['student:id,student_name', 'steps'])]);
    }

    public function destroy(Request $request, WorkManual $manual): JsonResponse
    {
        $this->authorize($request, $manual);
        $manual->delete();
        return response()->json(['data' => null]);
    }

    /**
     * 画像/動画アップロード (public ディスク)。
     */
    public function uploadMedia(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:51200|mimetypes:image/jpeg,image/png,image/webp,video/mp4,video/quicktime',
        ]);
        $path = $request->file('file')->store('work-manuals', 'public');
        return response()->json([
            'data' => [
                'path' => $path,
                'url'  => Storage::disk('public')->url($path),
            ],
        ]);
    }

    private function authorize(Request $request, WorkManual $manual): void
    {
        // ARCH-AUTH 統一: classroom_id 完全一致比較 (複数所属で誤拒否 / null バイパス)
        // を統一基盤 authorizeClassroomId に委譲する。
        $this->authorizeClassroomId($request->user(), $manual->classroom_id, '他事業所のマニュアルにはアクセスできません。');
    }
}
