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
        private \App\Services\OutcomeService $outcome,
        private \App\Services\AbilityProgressMapService $progressMap,
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

        // 1日3問: 今日回答済みの問いは結果表示にし、未回答を3問になるまで補充する。
        // 3問とも回答済みなら新しい問いは出さない(リロードしても同じ並び)。
        $today = Carbon::today()->toDateString();
        $todayObs = AbilityObservation::where('student_id', $student->id)
            ->whereDate('observed_date', $today)
            ->orderByDesc('id')
            ->get()
            ->unique('item_id')
            ->take(3)
            ->values();
        $answeredItemIds = $todayObs->pluck('item_id')->all();

        $needed = max(0, 3 - count($answeredItemIds));
        $fillItems = $needed > 0
            ? $this->questions->nextItemsFor($student, $needed, $answeredItemIds)
            : collect();

        // 回答済み表示用の内訳: 誰が・何時に・どの活動で記録したか。
        // 設問は「生徒×日」単位(1日3問)のため、同じ日の別の活動記録や別スタッフの
        // 回答でも「本日回答済」になる。内訳を明示しないと「回答していないのに
        // 回答済みになる」ように見えるため(現場報告)、出所を返して画面に表示する。
        $recorderNames = \App\Models\User::whereIn('id', $todayObs->pluck('recorded_by')->filter()->unique())
            ->pluck('full_name', 'id');
        $activityNames = \App\Models\DailyRecord::whereIn('id', $todayObs->pluck('daily_record_id')->filter()->unique())
            ->pluck('activity_name', 'id');

        $questions = [];
        // 未回答(これから答える問い)を先に
        foreach ($fillItems as $it) {
            $q = $this->questions->buildQuestion($student, $it);
            $q['answered'] = false;
            $q['answered_degree'] = null;
            $q['answered_at'] = null;
            $q['answered_by'] = null;
            $q['answered_in'] = null;
            $questions[] = $q;
        }
        // 今日回答済み(結果表示)。回答時の学年帯で表示する。
        foreach ($todayObs as $obs) {
            $item = \App\Models\AbilityEvalItem::where('item_id', $obs->item_id)->first();
            if (! $item) {
                continue;
            }
            $q = $this->questions->buildQuestion($student, $item, $obs->axis_id);
            $q['answered'] = true;
            $q['answered_degree'] = $obs->degree;
            $q['answered_at'] = $obs->created_at?->format('H:i');
            $q['answered_by'] = $obs->recorded_by ? ($recorderNames[$obs->recorded_by] ?? null) : null;
            $q['answered_in'] = $obs->daily_record_id ? ($activityNames[$obs->daily_record_id] ?? null) : null;
            $questions[] = $q;
        }

        if (empty($questions)) {
            return response()->json(['success' => true, 'data' => null, 'message' => '出題できる項目がありません。']);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'questions' => $questions,
                'question' => $questions[0], // 後方互換(単一参照)
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
            'degree' => 'nullable|integer|min:0|max:10',
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
        // 軸は「今取り組む学年帯」(到達で前進。出題時 buildQuestion と同じ算出)を用いる。
        $item = \App\Models\AbilityEvalItem::where('item_id', $validated['item_id'])->first();
        $observation = AbilityObservation::create([
            'classroom_id' => $student->classroom_id,
            'student_id' => $student->id,
            'daily_record_id' => $validated['daily_record_id'] ?? null,
            'item_id' => $validated['item_id'],
            'axis_id' => $item ? $this->questions->currentAxisFor($student, $item) : 'S1',
            'support_code' => $validated['support_code'] ?? null,
            'result' => $validated['result'] ?? null,
            'degree' => $validated['degree'] ?? null,
            'is_new_scene' => $validated['is_new_scene'] ?? false,
            'behavior' => $validated['behavior'] ?? null,
            'observed_date' => $validated['observed_date'] ?? Carbon::now()->toDateString(),
            'recorded_by' => $request->user()->id,
        ]);

        // 回答した瞬間にスコアを再計算し、到達マップ・全体像へ即反映する(手動更新不要)。
        $this->scoring->scoreStudent($student);

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
     * 到達マップ(項目×学年帯の到達状況＋期間の成長)を返す。
     */
    public function progressMap(Request $request, Student $student): JsonResponse
    {
        if ($deny = $this->guard($request, $student)) {
            return $deny;
        }

        $asOf = $request->filled('as_of') ? Carbon::parse($request->query('as_of')) : null;
        $since = $request->filled('since') ? Carbon::parse($request->query('since')) : null;

        return response()->json([
            'success' => true,
            'data' => $this->progressMap->forStudent($student, $since, $asOf),
        ]);
    }

    /**
     * 成果(outcome)を返す(S6): A スコアΔ / B モニタリング達成度 / C 主観×客観の一致。
     */
    public function outcome(Request $request, Student $student): JsonResponse
    {
        if ($deny = $this->guard($request, $student)) {
            return $deny;
        }

        return response()->json([
            'success' => true,
            'data' => $this->outcome->forStudent($student),
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
        $map = $this->progressMap->forStudent($student);
        $filename = 'ability_summary_' . ($student->student_name ?? $student->id) . '.pdf';

        return \App\Services\PuppeteerPdfService::download('pdf.ability-summary', [
            'student' => $student,
            'classroom' => $student->classroom,
            'summary' => $summary,
            'map' => $map,
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

        // 解除はそのまま保存。
        if ($code === null) {
            $student->update(['mynameis_member_code' => null, 'mynameis_linked_at' => null]);

            return response()->json([
                'success' => true,
                'data' => ['mynameis_member_code' => null],
                'message' => '紐づけを解除しました。',
            ]);
        }

        // 紐づけ前に mynameis の教室名を照会し、児童の教室名と一致する場合のみ紐づける。
        // 教室が異なる/メンバーID未存在/照会できない場合は紐づけを拒否する(保存しない)。
        $check = $this->verifyMynameisClassroom($code, $student);
        $student->loadMissing('classroom');
        $ownClass = $student->classroom?->classroom_name;

        if ($check['status'] === 'mismatch') {
            return response()->json([
                'success' => false,
                'message' => "mynameis の教室「{$check['organization_name']}」が児童の教室「{$ownClass}」と異なるため紐づけできません。",
            ], 422);
        }
        if ($check['status'] === 'not_found') {
            return response()->json([
                'success' => false,
                'message' => "メンバーID「{$code}」が mynameis に見つかりません。",
            ], 422);
        }
        if ($check['status'] === 'error') {
            return response()->json([
                'success' => false,
                'message' => 'mynameis に照会できませんでした。時間をおいて再度お試しください。',
            ], 503);
        }

        // 'ok'(教室一致) または 'unconfigured'(照会先未設定=検証不可だが許可) のみ保存。
        $student->update(['mynameis_member_code' => $code, 'mynameis_linked_at' => Carbon::now()]);

        return response()->json([
            'success' => true,
            'data' => [
                'mynameis_member_code' => $student->mynameis_member_code,
                'mynameis_classroom'   => $check['organization_name'],
                'classroom_matches'    => $check['status'] === 'ok' ? true : null,
            ],
            'message' => 'mynameis メンバーIDと紐づけました。',
        ]);
    }

    /**
     * mynameis にメンバーIDを照会し、教室一致の判定状態を返す。
     *
     * status: ok(教室一致) / mismatch(教室不一致) / not_found(ID未存在) /
     *         error(照会不可) / unconfigured(照会先未設定)。
     *
     * @return array{status: string, organization_name: ?string}
     */
    private function verifyMynameisClassroom(string $code, Student $student): array
    {
        $url = config('services.mynameis.resolve_url');
        $secret = config('services.mynameis.shared_secret');
        if (! $url || ! $secret) {
            return ['status' => 'unconfigured', 'organization_name' => null];
        }

        try {
            $res = \Illuminate\Support\Facades\Http::timeout(10)->acceptJson()
                ->post($url, ['secret' => $secret, 'member_code' => $code]);

            if ($res->status() === 404) {
                return ['status' => 'not_found', 'organization_name' => null];
            }
            if (! $res->successful()) {
                return ['status' => 'error', 'organization_name' => null];
            }
            $orgName = $res->json('data.organization_name');
            if ($orgName === null) {
                return ['status' => 'error', 'organization_name' => null];
            }

            $student->loadMissing('classroom');
            $matches = $student->classroom
                && trim((string) $orgName) === trim((string) $student->classroom->classroom_name);

            return ['status' => $matches ? 'ok' : 'mismatch', 'organization_name' => $orgName];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'organization_name' => null];
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
