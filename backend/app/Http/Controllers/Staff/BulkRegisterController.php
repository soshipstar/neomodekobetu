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
     * 生年月日から学年を計算する
     */
    private function calculateGradeLevel(string $birthDate, int $gradeAdjustment = 0): string
    {
        $birth = new \DateTime($birthDate);
        $now = new \DateTime();

        // 学齢計算: 4月1日基準
        $fiscalYear = (int) $now->format('Y');
        if ((int) $now->format('n') < 4) {
            $fiscalYear--;
        }

        $birthFiscalYear = (int) $birth->format('Y');
        if ((int) $birth->format('n') < 4 || ((int) $birth->format('n') === 4 && (int) $birth->format('j') === 1)) {
            $birthFiscalYear--;
        }

        $age = $fiscalYear - $birthFiscalYear;
        $adjustedAge = $age + $gradeAdjustment;

        if ($adjustedAge < 6) {
            return 'preschool';
        } elseif ($adjustedAge < 12) {
            return 'elementary';
        } elseif ($adjustedAge < 15) {
            return 'junior_high';
        } else {
            return 'high_school';
        }
    }

    /**
     * CSVまたはテキストデータを解析してプレビュー用データを返す
     * CSV形式: 保護者氏名,生徒氏名,生年月日,保護者メール,支援開始日,学年調整,月,火,水,木,金,土
     */
    public function parse(Request $request): JsonResponse
    {
        $user = $request->user();
        $lines = [];

        if ($request->hasFile('file')) {
            $content = file_get_contents($request->file('file')->getRealPath());
            // BOM除去
            if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
                $content = substr($content, 3);
            }
            // 文字コード変換 (Shift-JIS → UTF-8)
            $encoding = mb_detect_encoding($content, ['UTF-8', 'SJIS-win', 'SJIS', 'EUC-JP', 'ASCII'], true);
            if ($encoding && $encoding !== 'UTF-8') {
                $content = mb_convert_encoding($content, 'UTF-8', $encoding);
            }
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
        $dayLabels = ['月', '火', '水', '木', '金', '土'];

        foreach ($lines as $i => $line) {
            if (trim($line) === '') continue;

            $cols = str_getcsv($line, ',');
            // タブ区切りも試す
            if (count($cols) === 1) {
                $cols = str_getcsv($line, "\t");
            }

            $guardianName    = trim($cols[0] ?? '');
            $studentName     = trim($cols[1] ?? '');
            $birthDate       = trim($cols[2] ?? '');
            $guardianEmail   = trim($cols[3] ?? '');
            $supportStartDate = trim($cols[4] ?? '');
            $gradeAdjustment = isset($cols[5]) && $cols[5] !== '' ? (int) $cols[5] : 0;
            $scheduledMon    = (int) ($cols[6] ?? 0);
            $scheduledTue    = (int) ($cols[7] ?? 0);
            $scheduledWed    = (int) ($cols[8] ?? 0);
            $scheduledThu    = (int) ($cols[9] ?? 0);
            $scheduledFri    = (int) ($cols[10] ?? 0);
            $scheduledSat    = (int) ($cols[11] ?? 0);

            // 通所曜日の表示用テキスト
            $scheduledArr = [$scheduledMon, $scheduledTue, $scheduledWed, $scheduledThu, $scheduledFri, $scheduledSat];
            $scheduledDaysText = [];
            foreach ($scheduledArr as $idx => $val) {
                if ($val) {
                    $scheduledDaysText[] = $dayLabels[$idx];
                }
            }

            // 学年を自動計算
            $gradeLevel = '';
            if (! empty($birthDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthDate)) {
                try {
                    $gradeLevel = $this->calculateGradeLevel($birthDate, $gradeAdjustment);
                } catch (\Exception $e) {
                    $gradeLevel = '';
                }
            }

            $row = [
                'row_number'          => $i + 2,
                'guardian_name'       => $guardianName,
                'student_name'        => $studentName,
                'birth_date'          => $birthDate,
                'guardian_email'      => $guardianEmail,
                'support_start_date'  => $supportStartDate,
                'grade_adjustment'    => $gradeAdjustment,
                'grade_level'         => $gradeLevel,
                'scheduled_monday'    => (bool) $scheduledMon,
                'scheduled_tuesday'   => (bool) $scheduledTue,
                'scheduled_wednesday' => (bool) $scheduledWed,
                'scheduled_thursday'  => (bool) $scheduledThu,
                'scheduled_friday'    => (bool) $scheduledFri,
                'scheduled_saturday'  => (bool) $scheduledSat,
                'scheduled_days'      => implode('・', $scheduledDaysText) ?: '-',
                'status'              => 'valid',
                'errors'              => [],
            ];

            // バリデーション（保護者氏名、生徒氏名、生年月日が必須）
            if (empty($row['guardian_name'])) {
                $row['errors'][] = '保護者氏名は必須です。';
            }
            if (empty($row['student_name'])) {
                $row['errors'][] = '生徒氏名は必須です。';
            }
            if (empty($row['birth_date'])) {
                $row['errors'][] = '生年月日は必須です。';
            } elseif (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $row['birth_date'])) {
                $row['errors'][] = '生年月日はYYYY-MM-DD形式で入力してください。';
            }
            if (! empty($row['support_start_date']) && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $row['support_start_date'])) {
                $row['errors'][] = '支援開始日はYYYY-MM-DD形式で入力してください。';
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
     * 同じ保護者氏名は同一保護者として紐付ける
     */
    public function execute(Request $request): JsonResponse
    {
        $user = $request->user();
        $request->validate([
            'rows'                      => 'required|array|min:1',
            'rows.*.guardian_name'      => 'required|string|max:255',
            'rows.*.student_name'       => 'required|string|max:255',
            'rows.*.birth_date'         => 'required|date',
            'rows.*.guardian_email'     => 'nullable|email|max:255',
            'rows.*.support_start_date' => 'nullable|date',
            'rows.*.grade_adjustment'   => 'nullable|integer|min:-2|max:2',
            'rows.*.grade_level'        => 'nullable|string',
        ]);

        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        DB::transaction(function () use ($request, $user, &$successCount, &$errorCount, &$errors) {
            $classroomId = $user->classroom_id;
            $guardianMap = []; // 保護者氏名 => DB ID

            // guardian_XXX形式のユーザー名の最大番号を取得
            $lastGuardian = User::where('username', 'like', 'guardian_%')
                ->orderByRaw("CAST(SUBSTRING(username FROM 'guardian_(\\d+)') AS INTEGER) DESC NULLS LAST")
                ->first();
            $nextNumber = 1;
            if ($lastGuardian && preg_match('/guardian_(\d+)/', $lastGuardian->username, $matches)) {
                $nextNumber = (int) $matches[1] + 1;
            }

            foreach ($request->rows as $index => $row) {
                try {
                    $guardianName = $row['guardian_name'];
                    $gradeAdjustment = (int) ($row['grade_adjustment'] ?? 0);

                    // 保護者の処理（同一氏名は同一保護者として紐付け）
                    if (! isset($guardianMap[$guardianName])) {
                        $username = 'guardian_' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
                        $nextNumber++;

                        // ユーザー名の重複確認
                        while (User::where('username', $username)->exists()) {
                            $username = 'guardian_' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
                            $nextNumber++;
                        }

                        $password = $this->generateRandomPassword();

                        $guardian = User::create([
                            'classroom_id'  => $classroomId,
                            'username'      => $username,
                            'password'      => Hash::make($password),
                            'password_plain' => $password,
                            'full_name'     => $guardianName,
                            'email'         => $row['guardian_email'] ?: null,
                            'user_type'     => 'guardian',
                            'is_active'     => true,
                        ]);
                        $guardianMap[$guardianName] = $guardian->id;
                    } else {
                        // 既存保護者にメールが後から指定された場合は更新
                        if (! empty($row['guardian_email'])) {
                            $existingGuardian = User::find($guardianMap[$guardianName]);
                            if ($existingGuardian && empty($existingGuardian->email)) {
                                $existingGuardian->update(['email' => $row['guardian_email']]);
                            }
                        }
                    }

                    $guardianId = $guardianMap[$guardianName];

                    // 学年を計算
                    $gradeLevel = $row['grade_level'] ?? 'elementary';
                    if (! empty($row['birth_date'])) {
                        try {
                            $gradeLevel = $this->calculateGradeLevel($row['birth_date'], $gradeAdjustment);
                        } catch (\Exception $e) {
                            // fallback
                        }
                    }

                    // 生徒を作成
                    Student::create([
                        'classroom_id'        => $classroomId,
                        'guardian_id'         => $guardianId,
                        'student_name'        => $row['student_name'],
                        'birth_date'          => $row['birth_date'] ?: null,
                        'support_start_date'  => ! empty($row['support_start_date']) ? $row['support_start_date'] : null,
                        'grade_level'         => $gradeLevel,
                        'grade_adjustment'    => $gradeAdjustment,
                        'status'              => 'active',
                        'scheduled_monday'    => ! empty($row['scheduled_monday']),
                        'scheduled_tuesday'   => ! empty($row['scheduled_tuesday']),
                        'scheduled_wednesday' => ! empty($row['scheduled_wednesday']),
                        'scheduled_thursday'  => ! empty($row['scheduled_thursday']),
                        'scheduled_friday'    => ! empty($row['scheduled_friday']),
                        'scheduled_saturday'  => ! empty($row['scheduled_saturday']),
                        'scheduled_sunday'    => false,
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

    /**
     * ランダムなパスワードを生成（8文字の英数字）
     */
    private function generateRandomPassword(int $length = 8): string
    {
        $chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $password;
    }
}
