<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Admin\VacancyWidgetTokenController as AdminController;
use App\Http\Controllers\Controller;
use App\Models\Classroom;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * スタッフが /staff/waiting-list からも HP 埋め込みコードを操作できるよう、
 * 自教室の vacancy_widget_token を取得・発行・無効化する軽量エンドポイント。
 *
 * 認可: 認証済みスタッフ (sanctum)。操作対象は自分の classroom_id 固定。
 * Admin\VacancyWidgetTokenController と挙動・レスポンス形は揃える。
 */
class WidgetTokenController extends Controller
{
    /**
     * 自教室の埋め込みコード一式を返す (未発行なら token=null)。
     */
    public function show(Request $request): JsonResponse
    {
        $classroom = $this->ownClassroom($request);
        return response()->json([
            'success' => true,
            'data'    => $this->buildEmbedInfo($classroom),
        ]);
    }

    /**
     * トークンを発行 (or 再発行)。
     */
    public function store(Request $request): JsonResponse
    {
        $classroom = $this->ownClassroom($request);
        $classroom->vacancy_widget_token = $this->generateToken();
        $classroom->save();
        return response()->json([
            'success' => true,
            'data'    => $this->buildEmbedInfo($classroom),
            'message' => 'ウィジェットコードを発行しました。',
        ]);
    }

    /**
     * トークンを無効化する。
     */
    public function destroy(Request $request): JsonResponse
    {
        $classroom = $this->ownClassroom($request);
        $classroom->vacancy_widget_token = null;
        $classroom->save();
        return response()->json([
            'success' => true,
            'message' => 'ウィジェットコードを無効化しました。',
        ]);
    }

    /**
     * 自教室を解決する。classroom_id 未設定なら 403。
     */
    private function ownClassroom(Request $request): Classroom
    {
        $user = $request->user();
        if (!$user->classroom_id) {
            abort(403, '所属教室が設定されていません。');
        }
        $classroom = Classroom::find($user->classroom_id);
        if (!$classroom || !$classroom->is_active) {
            abort(404, '教室が見つかりません。');
        }
        return $classroom;
    }

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
     * Admin と同じ shape の埋め込み情報を返す。
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
