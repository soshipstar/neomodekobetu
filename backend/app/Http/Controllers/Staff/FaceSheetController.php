<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentFaceSheet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FaceSheetController extends Controller
{
    public function show(Request $request, Student $student): JsonResponse
    {
        $user = $request->user();
        if ($user->classroom_id && !in_array($student->classroom_id, $user->accessibleClassroomIds(), true)) {
            abort(403);
        }

        $sheet = StudentFaceSheet::where('student_id', $student->id)->first();

        return response()->json([
            'success' => true,
            'data' => $sheet,
        ]);
    }

    public function store(Request $request, Student $student): JsonResponse
    {
        $user = $request->user();
        if ($user->classroom_id && !in_array($student->classroom_id, $user->accessibleClassroomIds(), true)) {
            abort(403);
        }

        $validated = $request->validate([
            'daily_life' => 'nullable|array',
            'physical' => 'nullable|array',
            'profile' => 'nullable|array',
            'considerations' => 'nullable|array',
            'memo' => 'nullable|string',
        ]);

        $sheet = StudentFaceSheet::updateOrCreate(
            ['student_id' => $student->id],
            [
                'daily_life' => $validated['daily_life'] ?? null,
                'physical' => $validated['physical'] ?? null,
                'profile' => $validated['profile'] ?? null,
                'considerations' => $validated['considerations'] ?? null,
                'memo' => $validated['memo'] ?? null,
                'updated_by' => $user->id,
            ]
        );

        return response()->json([
            'success' => true,
            'data' => $sheet,
            'message' => 'フェイスシートを保存しました。',
        ]);
    }
}
