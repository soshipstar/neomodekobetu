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
     *
     * 受け付けるテーマクエリパラメータ (任意):
     *   theme=light|dark|warm|cool|minimal|brand   既定 brand (#14a898)
     *   primary=14a898    primary 色 (#なしの 3 or 6 桁 hex)
     *   bg=ffffff         背景色 (transparent も可)
     *   fg=1a1a1a         文字色
     *   radius=none|sm|md|lg   角丸度合い  既定 md
     *   compact=1         サイドバー用の縦長コンパクト表示
     *   header=0          教室名ヘッダを非表示
     */
    public function widget(Request $request, string $token): SymfonyResponse
    {
        $classroom = $this->resolveClassroom($token);
        $payload = $this->buildVacancyPayload($classroom);
        $theme = $this->resolveTheme($request);

        $html = view('widget.vacancy', [
            'classroom' => $classroom,
            'payload'   => $payload,
            'token'     => $token,
            'theme'     => $theme,
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
     * テーマパラメータを正規化して安全な値だけ返す。
     * 不正値は無視して既定にフォールバック (XSS / CSS injection 防御)。
     */
    private function resolveTheme(Request $request): array
    {
        $presets = [
            // [primary, bg, fg, mode]
            'brand'   => ['14a898', 'ffffff', '1a1a1a', 'light'],
            'light'   => ['2563eb', 'ffffff', '1a1a1a', 'light'],
            'dark'    => ['38bdf8', '111827', 'f9fafb', 'dark'],
            'warm'    => ['ea580c', 'fff7ed', '431407', 'light'],
            'cool'    => ['0891b2', 'ecfeff', '083344', 'light'],
            'minimal' => ['333333', 'ffffff', '111111', 'light'],
            'transparent' => ['14a898', 'transparent', '1a1a1a', 'light'],
        ];
        $themeKey = $request->query('theme', 'brand');
        if (!isset($presets[$themeKey])) $themeKey = 'brand';
        [$primary, $bg, $fg, $mode] = $presets[$themeKey];

        // 個別 override (preset を上書き)
        $primary = $this->sanitizeColor($request->query('primary'), $primary);
        $bg      = $this->sanitizeBackground($request->query('bg'), $bg);
        $fg      = $this->sanitizeColor($request->query('fg'), $fg);

        $radius = $request->query('radius', 'md');
        if (!in_array($radius, ['none', 'sm', 'md', 'lg'], true)) $radius = 'md';

        return [
            'preset'  => $themeKey,
            'primary' => $primary,
            'bg'      => $bg,
            'fg'      => $fg,
            'mode'    => $mode,
            'radius'  => $radius,
            'compact' => (bool) $request->query('compact', false),
            'header'  => $request->query('header', '1') !== '0',
        ];
    }

    /**
     * 16進カラー (3 桁または 6 桁) を厳格に検証する。# は前置でも可。
     * 不正なら fallback を返す。
     */
    private function sanitizeColor(?string $value, string $fallback): string
    {
        if (!is_string($value) || $value === '') return $fallback;
        $value = ltrim($value, '#');
        if (preg_match('/^[0-9a-fA-F]{3}$/', $value)) {
            // 3桁を6桁に展開
            return strtolower($value[0] . $value[0] . $value[1] . $value[1] . $value[2] . $value[2]);
        }
        if (preg_match('/^[0-9a-fA-F]{6}$/', $value)) {
            return strtolower($value);
        }
        return $fallback;
    }

    /**
     * 背景色は transparent も許可する。
     */
    private function sanitizeBackground(?string $value, string $fallback): string
    {
        if ($value === 'transparent') return 'transparent';
        return $this->sanitizeColor($value, $fallback);
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
