<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\ChatRoom;
use App\Models\Classroom;
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
     * CSV形式: 教室名,保護者氏名,生徒氏名,生年月日,保護者メール,支援開始日,学年調整,月,火,水,木,金,土
     * 教室名列が省略された旧形式（12列）も後方互換で受け付ける
     */
    /**
     * CSVヘッダー行から「フィールドキー → 列インデックス」の対応表を作る。
     * 認識できた列のみ格納する（ふりがな等の任意列に対応、列順は不問）。
     */
    private function buildHeaderMap(array $headerCells): array
    {
        $map = [];
        foreach ($headerCells as $index => $cell) {
            $key = $this->classifyHeader((string) $cell);
            if ($key !== null && ! isset($map[$key])) {
                $map[$key] = $index;
            }
        }
        return $map;
    }

    /**
     * ヘッダーセル名を正規化し、対応するフィールドキーを返す（不明なら null）。
     * 「保護者氏名（ふりがな）」「生徒氏名(ふりがな)」等の任意列に対応する。
     */
    private function classifyHeader(string $header): ?string
    {
        // 空白・全半角括弧・引用符を除去して判定
        $n = str_replace([' ', '　', '（', '）', '(', ')', '"'], '', trim($header));
        if ($n === '') {
            return null;
        }

        $isKana = str_contains($n, 'ふりがな') || str_contains($n, 'フリガナ')
            || str_contains($n, 'かな') || str_contains($n, 'カナ');

        if (str_contains($n, 'メール') || stripos($n, 'mail') !== false) {
            return 'guardian_email';
        }
        if (str_contains($n, '生年月日') || str_contains($n, '誕生')) {
            return 'birth_date';
        }
        if (str_contains($n, '支援開始')) {
            return 'support_start_date';
        }
        if (str_contains($n, '学年')) {
            return 'grade_adjustment';
        }
        if (str_contains($n, '教室')) {
            return 'classroom';
        }
        if (str_contains($n, '保護者')) {
            return $isKana ? 'guardian_name_kana' : 'guardian_name';
        }
        if (str_contains($n, '生徒') || str_contains($n, '児童')) {
            return $isKana ? 'student_name_kana' : 'student_name';
        }

        $dayMap = ['月' => 'day_mon', '火' => 'day_tue', '水' => 'day_wed', '木' => 'day_thu', '金' => 'day_fri', '土' => 'day_sat'];
        return $dayMap[$n] ?? null;
    }

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

        // ヘッダー行を取得し、列名で項目を対応付ける（ふりがな等の任意列・列順に対応）。
        $headerCells = str_getcsv(array_shift($lines), ',');
        if (count($headerCells) === 1) {
            $headerCells = str_getcsv((string) ($headerCells[0] ?? ''), "\t");
        }
        $colMap = $this->buildHeaderMap($headerCells);
        // 「保護者氏名」または「生徒氏名」をヘッダーで認識できればヘッダー方式、
        // できなければ従来の列位置方式にフォールバックする（旧CSV互換）。
        $useHeader = isset($colMap['guardian_name']) || isset($colMap['student_name']);

        $parsed = [];
        $dayLabels = ['月', '火', '水', '木', '金', '土'];

        // アクセス可能な教室の名前→IDマッピングを構築
        $accessibleIds = $user->switchableClassroomIds();
        $classroomMap = Classroom::whereIn('id', $accessibleIds)
            ->pluck('id', 'classroom_name')
            ->toArray();
        $defaultClassroomId = $user->classroom_id;
        $defaultClassroomName = Classroom::find($defaultClassroomId)?->classroom_name ?? '';

        foreach ($lines as $i => $line) {
            if (trim($line) === '') continue;

            $cols = str_getcsv($line, ',');
            // タブ区切りも試す
            if (count($cols) === 1) {
                $cols = str_getcsv($line, "\t");
            }

            if ($useHeader) {
                // ヘッダー名で対応付け（列順・任意列に強い。ふりがな対応）
                $get = function (string $key) use ($colMap, $cols): string {
                    return isset($colMap[$key]) ? trim((string) ($cols[$colMap[$key]] ?? '')) : '';
                };
                $classroomName    = $get('classroom');
                $guardianName     = $get('guardian_name');
                $guardianNameKana = $get('guardian_name_kana');
                $studentName      = $get('student_name');
                $studentNameKana  = $get('student_name_kana');
                $birthDate        = $get('birth_date');
                $guardianEmail    = $get('guardian_email');
                $supportStartDate = $get('support_start_date');
                $gradeAdjustment  = $get('grade_adjustment') !== '' ? (int) $get('grade_adjustment') : 0;
                $scheduledMon     = (int) ($get('day_mon') ?: 0);
                $scheduledTue     = (int) ($get('day_tue') ?: 0);
                $scheduledWed     = (int) ($get('day_wed') ?: 0);
                $scheduledThu     = (int) ($get('day_thu') ?: 0);
                $scheduledFri     = (int) ($get('day_fri') ?: 0);
                $scheduledSat     = (int) ($get('day_sat') ?: 0);
            } else {
                // 従来の列位置方式（旧CSV互換、ふりがな列なし）
                // 13列以上 = 新形式（教室名あり）、12列以下 = 旧形式
                $hasClassroomCol = count($cols) >= 13;
                $offset = $hasClassroomCol ? 1 : 0;
                $classroomName    = $hasClassroomCol ? trim($cols[0] ?? '') : '';
                $guardianName     = trim($cols[$offset + 0] ?? '');
                $guardianNameKana = '';
                $studentName      = trim($cols[$offset + 1] ?? '');
                $studentNameKana  = '';
                $birthDate        = trim($cols[$offset + 2] ?? '');
                $guardianEmail    = trim($cols[$offset + 3] ?? '');
                $supportStartDate = trim($cols[$offset + 4] ?? '');
                $gradeAdjustment  = isset($cols[$offset + 5]) && $cols[$offset + 5] !== '' ? (int) $cols[$offset + 5] : 0;
                $scheduledMon     = (int) ($cols[$offset + 6] ?? 0);
                $scheduledTue     = (int) ($cols[$offset + 7] ?? 0);
                $scheduledWed     = (int) ($cols[$offset + 8] ?? 0);
                $scheduledThu     = (int) ($cols[$offset + 9] ?? 0);
                $scheduledFri     = (int) ($cols[$offset + 10] ?? 0);
                $scheduledSat     = (int) ($cols[$offset + 11] ?? 0);
            }

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

            // 教室名の解決
            $resolvedClassroomId = $defaultClassroomId;
            $resolvedClassroomName = $defaultClassroomName;
            if (!empty($classroomName)) {
                if (isset($classroomMap[$classroomName])) {
                    $resolvedClassroomId = $classroomMap[$classroomName];
                    $resolvedClassroomName = $classroomName;
                } else {
                    $resolvedClassroomId = null;
                    $resolvedClassroomName = $classroomName;
                }
            }

            $row = [
                'row_number'          => $i + 2,
                'classroom_name'      => $resolvedClassroomName,
                'classroom_id'        => $resolvedClassroomId,
                'guardian_name'       => $guardianName,
                'guardian_name_kana'  => $guardianNameKana,
                'student_name'        => $studentName,
                'student_name_kana'   => $studentNameKana,
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
            if (!empty($classroomName) && $resolvedClassroomId === null) {
                $row['errors'][] = "教室「{$classroomName}」が見つからないか、アクセス権限がありません。";
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
            'rows.*.guardian_name_kana' => 'nullable|string|max:255',
            'rows.*.student_name'       => 'required|string|max:255',
            'rows.*.student_name_kana'  => 'nullable|string|max:255',
            'rows.*.birth_date'         => 'required|date',
            'rows.*.guardian_email'     => 'nullable|email|max:255',
            'rows.*.support_start_date' => 'nullable|date',
            'rows.*.grade_adjustment'   => 'nullable|integer|min:-2|max:2',
            'rows.*.grade_level'        => 'nullable|string',
            'rows.*.classroom_id'       => 'nullable|integer|exists:classrooms,id',
        ]);

        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        DB::transaction(function () use ($request, $user, &$successCount, &$errorCount, &$errors) {
            $defaultClassroomId = $user->classroom_id;
            $accessibleIds = $user->switchableClassroomIds();
            $guardianMap = []; // "保護者氏名_classroomId" => DB ID

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

                    // 行ごとの教室IDを決定（CSVで指定されていればそれを使用、なければデフォルト）
                    $rowClassroomId = $row['classroom_id'] ?? $defaultClassroomId;
                    if (!in_array((int) $rowClassroomId, $accessibleIds, true)) {
                        throw new \RuntimeException("教室ID {$rowClassroomId} へのアクセス権限がありません。");
                    }

                    // 保護者の処理（同一氏名は同一保護者として紐付け＝教室が違っても1アカウント）
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
                            'classroom_id'  => $rowClassroomId,
                            'username'      => $username,
                            'password'      => Hash::make($password),
                            'password_plain' => $password,
                            'full_name'     => $guardianName,
                            'full_name_kana' => ($row['guardian_name_kana'] ?? '') ?: null,
                            'email'         => $row['guardian_email'] ?: null,
                            'user_type'     => 'guardian',
                            'is_active'     => true,
                        ]);
                        $guardianMap[$guardianName] = $guardian->id;
                    } else {
                        // 既存保護者にメール/ふりがなが後から指定された場合は補完更新
                        $existingGuardian = User::find($guardianMap[$guardianName]);
                        if ($existingGuardian) {
                            $updates = [];
                            if (! empty($row['guardian_email']) && empty($existingGuardian->email)) {
                                $updates['email'] = $row['guardian_email'];
                            }
                            if (! empty($row['guardian_name_kana']) && empty($existingGuardian->full_name_kana)) {
                                $updates['full_name_kana'] = $row['guardian_name_kana'];
                            }
                            if (! empty($updates)) {
                                $existingGuardian->update($updates);
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

                    // 生徒を作成（CSVの教室に所属）
                    $student = Student::create([
                        'classroom_id'        => $rowClassroomId,
                        'guardian_id'         => $guardianId,
                        'student_name'        => $row['student_name'],
                        'student_name_kana'   => ($row['student_name_kana'] ?? '') ?: null,
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

                    ChatRoom::firstOrCreate([
                        'student_id'  => $student->id,
                        'guardian_id' => $guardianId,
                    ]);

                    // アセスメント期間の自動生成（支援開始日が設定されている場合）
                    if (! empty($row['support_start_date'])) {
                        try {
                            $assessmentService = app(\App\Services\AssessmentService::class);
                            $assessmentService->generateAssessmentPeriodsForStudent($student->id, $row['support_start_date']);
                        } catch (\Exception $e) {
                            \Illuminate\Support\Facades\Log::warning("一括登録: アセスメント生成エラー（{$row['student_name']}）: " . $e->getMessage());
                        }
                    }

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
