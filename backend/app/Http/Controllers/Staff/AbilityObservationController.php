<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\AbilityObservation;
use App\Models\AbilityScore;
use App\Models\AbilitySupportCode;
use App\Models\Classroom;
use App\Models\Student;
use App\Services\AbilityQuestionService;
use App\Services\AbilityScoringService;
use App\Services\AbilitySummaryService;
use App\Support\AbilityToolScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * 能力評価 P2: 日々の活動記録から児童ごとに1問の設問へ回答し、観察記録を保存する。
 *
 * 対象は能力評価トグル(classrooms.ability_assessment_enabled)が ON の教室のみ。
 * 児童の所属教室がスタッフのアクセス可能教室であることを必須にする(越境防止)。
 */
class AbilityObservationController extends Controller
{
    /** 結果の選択肢(評価表の「結果」: 完了/途中/拒否)。 */
    public const RESULTS = ['completed', 'partial', 'refused'];

    public function __construct(
        private AbilityQuestionService $questions,
        private AbilityScoringService $scoring,
        private AbilitySummaryService $summary,
    ) {
    }

    /**
     * 児童の次の設問を取得する。
     */
    public function nextQuestion(Request $request, Student $student): JsonResponse
    {
        if ($deny = $this->guard($request, $student)) {
            return $deny;
        }

        $item = $this->questions->nextItemFor($student, $request->query('exclude_item_id'));
        if (! $item) {
            return response()->json(['success' => true, 'data' => null, 'message' => '出題できる項目がありません。']);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'question' => $this->questions->buildQuestion($student, $item),
                'support_codes' => AbilitySupportCode::orderBy('sort_order')
                    ->get(['code', 'content', 'score_band']),
                'results' => self::RESULTS,
            ],
        ]);
    }

    /**
     * 観察記録を保存する。
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'item_id' => 'required|exists:ability_eval_items,item_id',
            'support_code' => 'nullable|exists:ability_support_codes,code',
            'result' => 'nullable|in:' . implode(',', self::RESULTS),
            'is_new_scene' => 'sometimes|boolean',
            'behavior' => 'nullable|string|max:2000',
            'daily_record_id' => 'nullable|exists:daily_records,id',
            'observed_date' => 'nullable|date',
        ]);

        $student = Student::findOrFail($validated['student_id']);
        if ($deny = $this->guard($request, $student)) {
            return $deny;
        }

        // 評価軸・教室・日付はサーバ側で確定する(クライアントを信用しない)。
        // 軸は項目のツール(DEV=成長段階/ADV=水準/WRK・UNV=時期)に応じて決める。
        $toolId = \App\Models\AbilityEvalItem::where('item_id', $validated['item_id'])->value('tool_id');
        $observation = AbilityObservation::create([
            'classroom_id' => $student->classroom_id,
            'student_id' => $student->id,
            'daily_record_id' => $validated['daily_record_id'] ?? null,
            'item_id' => $validated['item_id'],
            'axis_id' => AbilityToolScope::axisFor($student, $toolId ?? 'DEV'),
            'support_code' => $validated['support_code'] ?? null,
            'result' => $validated['result'] ?? null,
            'is_new_scene' => $validated['is_new_scene'] ?? false,
            'behavior' => $validated['behavior'] ?? null,
            'observed_date' => $validated['observed_date'] ?? Carbon::now()->toDateString(),
            'recorded_by' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'data' => $observation->load('item:item_id,name,domain', 'supportCode:code,content'),
            'message' => '観察記録を保存しました。',
        ], 201);
    }

    /**
     * 児童の最近の観察記録を返す(履歴表示用)。
     */
    public function recent(Request $request, Student $student): JsonResponse
    {
        if ($deny = $this->guard($request, $student)) {
            return $deny;
        }

        $observations = AbilityObservation::where('student_id', $student->id)
            ->with('item:item_id,name,domain', 'supportCode:code,content')
            ->orderByDesc('observed_date')
            ->orderByDesc('id')
            ->limit($request->integer('limit', 20))
            ->get();

        return response()->json(['success' => true, 'data' => $observations]);
    }

    /**
     * 観察記録からスコアを再計算し、変化した項目を ability_scores に追記する。
     */
    public function recomputeScores(Request $request, Student $student): JsonResponse
    {
        if ($deny = $this->guard($request, $student)) {
            return $deny;
        }

        $results = $this->scoring->scoreStudent($student);

        return response()->json([
            'success' => true,
            'data' => [
                'results' => $results,
                'scored' => collect($results)->where('status', 'scored')->count(),
                'needs_review' => collect($results)->where('needs_review', true)->count(),
            ],
            'message' => '能力評価スコアを更新しました。',
        ]);
    }

    /**
     * 児童の項目ごとの最新スコアを返す(全体像表示用)。
     */
    public function scores(Request $request, Student $student): JsonResponse
    {
        if ($deny = $this->guard($request, $student)) {
            return $deny;
        }

        $latest = AbilityScore::where('student_id', $student->id)
            ->with('item:item_id,name,domain')
            ->orderByDesc('evaluated_on')->orderByDesc('id')
            ->get()
            ->unique('item_id')
            ->values();

        return response()->json(['success' => true, 'data' => $latest]);
    }

    /**
     * 児童の「評価状況の全体像」(レーダー+詳細表)を返す。別添・閲覧用。
     */
    public function summary(Request $request, Student $student): JsonResponse
    {
        if ($deny = $this->guard($request, $student)) {
            return $deny;
        }

        return response()->json([
            'success' => true,
            'data' => $this->summary->forStudent($student),
        ]);
    }

    /**
     * 評価状況の全体像を PDF(別添)で出力する。
     */
    public function summaryPdf(Request $request, Student $student)
    {
        if ($deny = $this->guard($request, $student)) {
            return $deny;
        }

        $student->loadMissing('classroom');
        $summary = $this->summary->forStudent($student);
        $filename = 'ability_summary_' . ($student->student_name ?? $student->id) . '.pdf';

        return \App\Services\PuppeteerPdfService::download('pdf.ability-summary', [
            'student' => $student,
            'classroom' => $student->classroom,
            'summary' => $summary,
            'generatedOn' => Carbon::now()->toDateString(),
        ], $filename, 'A4', false);
    }

    /**
     * 児童に mynameis のメンバーID(member_code, 例 ABC12345)を紐づける(空で解除)。
     */
    public function linkMynameis(Request $request, Student $student): JsonResponse
    {
        if ($deny = $this->guard($request, $student)) {
            return $deny;
        }

        $validated = $request->validate([
            'mynameis_member_code' => 'nullable|string|max:16',
        ]);

        $code = isset($validated['mynameis_member_code'])
            ? strtoupper(trim($validated['mynameis_member_code']))
            : null;
        $code = ($code === '' ? null : $code);

        $student->update([
            'mynameis_member_code' => $code,
            'mynameis_linked_at' => $code ? Carbon::now() : null,
        ]);

        // メンバーIDから mynameis の教室(組織)名を照会し、児童の教室名と一致するか確認する。
        $verification = $code
            ? $this->resolveMynameisClassroom($code, $student)
            : ['organization_name' => null, 'matches' => null];

        return response()->json([
            'success' => true,
            'data' => [
                'mynameis_member_code' => $student->mynameis_member_code,
                'mynameis_classroom'   => $verification['organization_name'],
                'classroom_matches'    => $verification['matches'],
            ],
            'message' => ($student->mynameis_member_code ? 'mynameis メンバーIDと紐づけました。' : '紐づけを解除しました。'),
        ]);
    }

    /**
     * mynameis にメンバーIDを照会し、組織(教室)名と、児童の教室名との一致可否を返す。
     * 照会先未設定・通信失敗時は null(確認できず)を返す。
     *
     * @return array{organization_name: ?string, matches: ?bool}
     */
    private function resolveMynameisClassroom(string $code, Student $student): array
    {
        $url = config('services.mynameis.resolve_url');
        $secret = config('services.mynameis.shared_secret');
        if (! $url || ! $secret) {
            return ['organization_name' => null, 'matches' => null];
        }

        try {
            $res = \Illuminate\Support\Facades\Http::timeout(10)->acceptJson()
                ->post($url, ['secret' => $secret, 'member_code' => $code]);
            if (! $res->successful()) {
                return ['organization_name' => null, 'matches' => null];
            }
            $orgName = $res->json('data.organization_name');
            $student->loadMissing('classroom');
            $matches = $orgName !== null && $student->classroom
                && trim((string) $orgName) === trim((string) $student->classroom->classroom_name);

            return ['organization_name' => $orgName, 'matches' => $matches];
        } catch (\Throwable $e) {
            return ['organization_name' => null, 'matches' => null];
        }
    }

    /**
     * 認可: 児童の所属教室がアクセス可能 かつ 能力評価トグルが ON であること。
     * 不可なら JsonResponse を返し、OK なら null を返す。
     */
    private function guard(Request $request, Student $student): ?JsonResponse
    {
        $user = $request->user();

        if (! $student->classroom_id || ! in_array($student->classroom_id, $user->accessibleClassroomIds(), true)) {
            return response()->json(['success' => false, 'message' => 'この児童の能力評価を操作する権限がありません。'], 403);
        }

        $enabled = Classroom::whereKey($student->classroom_id)->value('ability_assessment_enabled');
        if (! $enabled) {
            return response()->json(['success' => false, 'message' => 'この事業所では能力評価システムが有効になっていません。'], 409);
        }

        return null;
    }
}
