<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClassroomSettingController extends Controller
{
    /**
     * 教室設定一覧を取得
     */
    public function index(Request $request): JsonResponse
    {
        $query = Classroom::where('is_active', true);

        if ($request->filled('classroom_id')) {
            $query->where('id', $request->classroom_id);
        }

        $classrooms = $query->get()->map(function ($classroom) {
            return [
                'id'             => $classroom->id,
                'classroom_name' => $classroom->classroom_name,
                'address'        => $classroom->address,
                'phone'          => $classroom->phone,
                'settings'       => $classroom->settings,
                'logo_path'      => $classroom->logo_path,
                'is_active'      => $classroom->is_active,
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $classrooms,
        ]);
    }

    /**
     * 教室設定を更新
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'classroom_id' => 'required|exists:classrooms,id',
            'settings'     => 'nullable|array',
            'address'      => 'nullable|string|max:500',
            'phone'        => 'nullable|string|max:20',
        ]);

        $classroom = Classroom::findOrFail($validated['classroom_id']);

        $updateData = [];
        if (isset($validated['settings'])) {
            $updateData['settings'] = $validated['settings'];
        }
        if (isset($validated['address'])) {
            $updateData['address'] = $validated['address'];
        }
        if (isset($validated['phone'])) {
            $updateData['phone'] = $validated['phone'];
        }

        $classroom->update($updateData);

        return response()->json([
            'success' => true,
            'data'    => $classroom->fresh(),
            'message' => '教室設定を更新しました。',
        ]);
    }
}
