<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\ClassroomCapacity;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * HP 埋め込みウィジェット用 公開エンドポイント。
 *
 * 教室が自社 HP に iframe で空き状況を表示するためのエンドポイント群。
 * 認証不要 (トークンで保護)、CORS / iframe を任意の外部サイトから許可する。
 *
 * 提供エンドポイント:
 *   GET /api/widget/vacancy/{token}        - 表示用 HTML (iframe で読み込む)
 *   GET /api/widget/vacancy/{token}/data   - JSON データのみ (JS ウィジェット用)
 *
 * セキュリティ方針:
 *   - 個人情報 (児童名・保護者名等) は一切返さない
 *   - 公開するのは「曜日別の空き数 / 定員 / 開所状況」のみ
 *   - トークンは 32 文字以上のランダム値。漏洩時は admin が再発行できる
 *   - レート制限は api ミドルウェア (180 req/min) で抑止
 */
class PublicVacancyWidgetController extends Controller
{
    /**
     * 曜日別空き状況の JSON データを返す。
     */
    public function data(string $token): JsonResponse
    {
        $classroom = $this->resolveClassroom($token);
        $payload = $this->buildVacancyPayload($classroom);

        return response()->json($payload)
            ->header('Cache-Control', 'public, max-age=60, must-revalidate')
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET');
    }

    /**
     * iframe で外部 HP に埋め込むための装飾済み HTML を返す。
     * meta refresh + 60 秒の fetch ポーリングでほぼリアルタイム反映する。
     */
    public function widget(string $token): SymfonyResponse
    {
        $classroom = $this->resolveClassroom($token);
        $payload = $this->buildVacancyPayload($classroom);

        $html = view('widget.vacancy', [
            'classroom' => $classroom,
            'payload'   => $payload,
            'token'     => $token,
        ])->render();

        return response($html, 200)
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=60, must-revalidate')
            // 任意の外部 HP から iframe 埋め込みを許可する。
            // production.conf の `add_header X-Frame-Options "SAMEORIGIN" always` は
            // nginx 側の location ブロックで上書きする (production.conf に追記済み)。
            ->header('Content-Security-Policy', "frame-ancestors *");
    }

    /**
     * トークンから教室を解決する。無効なら 404。
     */
    private function resolveClassroom(string $token): Classroom
    {
        if (!preg_match('/^[A-Za-z0-9_\-]{16,64}$/', $token)) {
            abort(404, 'Widget token not found.');
        }
        $classroom = Classroom::where('vacancy_widget_token', $token)
            ->where('is_active', true)
            ->first();
        if (!$classroom) {
            abort(404, 'Widget token not found.');
        }
        return $classroom;
    }

    /**
     * 曜日別の空き状況 payload を組み立てる。
     * Admin\WaitingListController::summary と同じ計算ロジックだが、
     * 個人情報を含めない (集計値のみ)。
     */
    private function buildVacancyPayload(Classroom $classroom): array
    {
        $days = [
            ['key' => 'monday',    'dow' => 1, 'label' => '月'],
            ['key' => 'tuesday',   'dow' => 2, 'label' => '火'],
            ['key' => 'wednesday', 'dow' => 3, 'label' => '水'],
            ['key' => 'thursday',  'dow' => 4, 'label' => '木'],
            ['key' => 'friday',    'dow' => 5, 'label' => '金'],
            ['key' => 'saturday',  'dow' => 6, 'label' => '土'],
            ['key' => 'sunday',    'dow' => 0, 'label' => '日'],
        ];

        $capacities = ClassroomCapacity::where('classroom_id', $classroom->id)
            ->get()
            ->keyBy('day_of_week');

        $result = [];
        foreach ($days as $d) {
            $cap = $capacities->get($d['dow']);
            $maxCapacity = $cap ? (int) $cap->max_capacity : 10;
            $isOpen = $cap ? (bool) $cap->is_open : true;

            if (!$isOpen) {
                $result[] = [
                    'day'          => $d['key'],
                    'label'        => $d['label'],
                    'is_open'      => false,
                    'max_capacity' => $maxCapacity,
                    'enrolled'     => 0,
                    'available'    => 0,
                    'status'       => 'closed',
                    'status_label' => '休業',
                ];
                continue;
            }

            $enrolled = Student::where('classroom_id', $classroom->id)
                ->whereIn('status', ['active', 'trial', 'short_term'])
                ->where('is_active', true)
                ->where("scheduled_{$d['key']}", true)
                ->count();
            $available = max(0, $maxCapacity - $enrolled);

            // 状態判定:
            //   available 0     → 満員 (full)
            //   available 1-2  → わずか (limited)
            //   available >= 3 → 空きあり (open)
            $status = $available === 0 ? 'full' : ($available <= 2 ? 'limited' : 'open');
            $statusLabel = $available === 0
                ? '満員'
                : ($available <= 2 ? "残り{$available}名" : '空きあり');

            $result[] = [
                'day'          => $d['key'],
                'label'        => $d['label'],
                'is_open'      => true,
                'max_capacity' => $maxCapacity,
                'enrolled'     => $enrolled,
                'available'    => $available,
                'status'       => $status,
                'status_label' => $statusLabel,
            ];
        }

        return [
            'classroom' => [
                'name'    => $classroom->classroom_name,
                'address' => $classroom->address,
                'phone'   => $classroom->phone,
            ],
            'days'       => $result,
            'updated_at' => now()->toIso8601String(),
            'note'       => '空き状況は1日1回〜数分単位で更新されます。最新の状況は教室までお問い合わせください。',
        ];
    }
}
