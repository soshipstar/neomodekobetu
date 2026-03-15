<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class BulkRegisterController extends Controller
{
    /**
     * CSVまたはテキストデータを解析してプレビュー用データを返す
     */
    public function parse(Request $request): JsonResponse
    {
        $user = $request->user();
        $lines = [];

        if ($request->hasFile('file')) {
            $content = file_get_contents($request->file('file')->getRealPath());
            $lines = preg_split('/\r?\n/', trim($content));
        } elseif ($request->filled('text')) {
            $lines = preg_split('/\r?\n/', trim($request->text));
        } else {
            return response()->json(['success' => false, 'message' => 'データが提供されていません。'], 422);
        }

        if (count($lines) < 2) {
            return response()->json(['success' => false, 'message' => 'ヘッダー行とデータ行が必要です。'], 422);
        }

        // ヘッダー行をスキップ
        $header = str_getcsv(array_shift($lines), ',');
        $parsed = [];

        foreach ($lines as $i => $line) {
            if (trim($line) === '') continue;

            $cols = str_getcsv($line, ',');
            // タブ区切りも試す
            if (count($cols) === 1) {
                $cols = str_getcsv($line, "\t");
            }

            $row = [
                'row_number'     => $i + 2,
                'student_name'   => trim($cols[0] ?? ''),
                'birth_date'     => trim($cols[1] ?? ''),
                'grade_level'    => trim($cols[2] ?? ''),
                'guardian_name'  => trim($cols[3] ?? ''),
                'guardian_email' => trim($cols[4] ?? ''),
                'status'         => 'valid',
                'errors'         => [],
            ];

            // バリデーション
            if (empty($row['student_name'])) {
                $row['errors'][] = '生徒名は必須です。';
            }
            if (empty($row['grade_level'])) {
                $row['errors'][] = '学年は必須です。';
            } elseif (! in_array($row['grade_level'], ['preschool', 'elementary', 'junior_high', 'high_school'])) {
                $row['errors'][] = '学年はpreschool/elementary/junior_high/high_schoolのいずれかです。';
            }
            if (! empty($row['birth_date']) && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $row['birth_date'])) {
                $row['errors'][] = '生年月日はYYYY-MM-DD形式で入力してください。';
            }
            if (! empty($row['guardian_email']) && ! filter_var($row['guardian_email'], FILTER_VALIDATE_EMAIL)) {
                $row['errors'][] = 'メールアドレスの形式が不正です。';
            }
            if (! empty($row['guardian_email']) && User::where('email', $row['guardian_email'])->exists()) {
                $row['errors'][] = 'このメールアドレスは既に登録されています。';
            }

            if (! empty($row['errors'])) {
                $row['status'] = 'error';
            }

            $parsed[] = $row;
        }

        return response()->json([
            'success' => true,
            'data'    => $parsed,
        ]);
    }

    /**
     * 解析済みデータを実行して生徒＋保護者を一括登録
     */
    public function execute(Request $request): JsonResponse
    {
        $user = $request->user();
        $request->validate([
            'rows'                  => 'required|array|min:1',
            'rows.*.student_name'   => 'required|string|max:255',
            'rows.*.birth_date'     => 'nullable|date',
            'rows.*.grade_level'    => 'required|string',
            'rows.*.guardian_name'  => 'nullable|string|max:255',
            'rows.*.guardian_email' => 'nullable|email|max:255',
        ]);

        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        DB::transaction(function () use ($request, $user, &$successCount, &$errorCount, &$errors) {
            foreach ($request->rows as $index => $row) {
                try {
                    $classroomId = $user->classroom_id;

                    // 保護者を作成（名前またはメールが提供された場合）
                    $guardianId = null;
                    if (! empty($row['guardian_name']) || ! empty($row['guardian_email'])) {
                        $guardianName = $row['guardian_name'] ?: ($row['student_name'] . 'の保護者');
                        $username = 'g_' . Str::slug($row['guardian_email'] ?: $guardianName) . '_' . Str::random(4);

                        $guardian = User::create([
                            'classroom_id' => $classroomId,
                            'username'     => $username,
                            'password'     => Hash::make(Str::random(8)),
                            'full_name'    => $guardianName,
                            'email'        => $row['guardian_email'] ?: null,
                            'user_type'    => 'guardian',
                            'is_active'    => true,
                        ]);
                        $guardianId = $guardian->id;
                    }

                    // 生徒を作成
                    Student::create([
                        'classroom_id' => $classroomId,
                        'guardian_id'  => $guardianId,
                        'student_name' => $row['student_name'],
                        'birth_date'   => $row['birth_date'] ?: null,
                        'grade_level'  => $row['grade_level'],
                        'status'       => 'active',
                    ]);

                    $successCount++;
                } catch (\Exception $e) {
                    $errorCount++;
                    $errors[] = [
                        'row'     => $row['row_number'] ?? ($index + 1),
                        'message' => $e->getMessage(),
                    ];
                }
            }
        });

        return response()->json([
            'success' => true,
            'data'    => [
                'success_count' => $successCount,
                'error_count'   => $errorCount,
                'errors'        => $errors,
            ],
        ]);
    }
}
