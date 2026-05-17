<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * 教室の HP 埋め込みウィジェット用トークンの発行・再発行を管理する。
 *
 * 各 admin が自分の閲覧可能な教室について、空き状況ウィジェットの
 * 公開 URL / iframe コードを取得・再発行できる。
 *
 * 認可:
 *   - master admin: 全教室
 *   - 通常 admin:  自分の所属企業の教室
 */
class VacancyWidgetTokenController extends Controller
{
    /**
     * 自分が閲覧可能な教室一覧と、各教室のトークン・埋め込みコードを返す。
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $isMaster = (bool) ($user->is_master ?? false);

        $query = Classroom::query()->where('is_active', true);

        if (!$isMaster) {
            // 通常 admin: 自社 (= 自教室の company_id) の教室
            $companyId = $user->classroom?->company_id;
            if ($companyId === null) {
                return response()->json(['success' => true, 'data' => []]);
            }
            $query->where('company_id', $companyId);
        }

        $classrooms = $query->orderBy('classroom_name')->get([
            'id', 'classroom_name', 'company_id', 'vacancy_widget_token',
        ]);

        $data = $classrooms->map(fn (Classroom $c) => $this->buildEmbedInfo($c));

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    /**
     * 指定教室のトークンを発行 or 再発行する。
     * 旧トークンが既にあった場合、それは無効化される (既に HP に貼られた iframe は壊れる)。
     */
    public function store(Request $request, Classroom $classroom): JsonResponse
    {
        $this->authorize($request->user(), $classroom);

        $classroom->vacancy_widget_token = $this->generateToken();
        $classroom->save();

        return response()->json([
            'success' => true,
            'data'    => $this->buildEmbedInfo($classroom),
            'message' => 'ウィジェットコードを発行しました。',
        ]);
    }

    /**
     * トークンを無効化する (null に戻す)。
     * 既に HP に貼られた iframe は 404 を返すようになる。
     */
    public function destroy(Request $request, Classroom $classroom): JsonResponse
    {
        $this->authorize($request->user(), $classroom);

        $classroom->vacancy_widget_token = null;
        $classroom->save();

        return response()->json([
            'success' => true,
            'message' => 'ウィジェットコードを無効化しました。',
        ]);
    }

    /**
     * 教室への閲覧/編集権限をチェック。NG なら 403 abort。
     */
    private function authorize($user, Classroom $classroom): void
    {
        $isMaster = (bool) ($user->is_master ?? false);
        if ($isMaster) return;

        $companyId = $user->classroom?->company_id;
        if ($companyId === null || $companyId !== $classroom->company_id) {
            abort(403, 'この教室のウィジェットコードを操作する権限がありません。');
        }
    }

    /**
     * URL-safe な 32 文字のランダムトークンを生成する。
     * 衝突確率は (32 = log2(64^32) ≈ 192bit) で実質ゼロだが、
     * 万一の衝突に備えて DB の UNIQUE 制約と組み合わせる前提でループする。
     */
    private function generateToken(): string
    {
        for ($i = 0; $i < 5; $i++) {
            $token = Str::random(32);
            if (!Classroom::where('vacancy_widget_token', $token)->exists()) {
                return $token;
            }
        }
        throw new \RuntimeException('Failed to generate a unique widget token.');
    }

    /**
     * 埋め込みコード一式 (URL + iframe HTML) を組み立てる。
     */
    private function buildEmbedInfo(Classroom $classroom): array
    {
        $base = config('app.url', 'https://kiduri.xyz');
        $token = $classroom->vacancy_widget_token;

        if (!$token) {
            return [
                'classroom_id'   => $classroom->id,
                'classroom_name' => $classroom->classroom_name,
                'token'          => null,
                'widget_url'     => null,
                'data_url'       => null,
                'iframe_html'    => null,
            ];
        }

        $widgetUrl = rtrim($base, '/') . "/api/widget/vacancy/{$token}";
        $dataUrl   = rtrim($base, '/') . "/api/widget/vacancy/{$token}/data";

        $iframeHtml = sprintf(
            '<iframe src="%s" width="100%%" height="430" style="border:0;max-width:640px;" title="%s 空き状況" loading="lazy"></iframe>',
            e($widgetUrl),
            e($classroom->classroom_name),
        );

        return [
            'classroom_id'   => $classroom->id,
            'classroom_name' => $classroom->classroom_name,
            'token'          => $token,
            'widget_url'     => $widgetUrl,
            'data_url'       => $dataUrl,
            'iframe_html'    => $iframeHtml,
        ];
    }
}
