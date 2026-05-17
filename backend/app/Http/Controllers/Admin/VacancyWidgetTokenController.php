<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
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
     * 指定された HP の URL を取得し、`<meta name="theme-color">` などから
     * 推奨テーマカラーを抽出して返す。
     *
     * 失敗してもエラーにせず、抽出できた限りの情報を返す (suggested は
     * null の場合あり)。
     *
     * セキュリティ:
     *   - 公開 HTTP(S) URL のみ許可
     *   - localhost/private IP への SSRF を防ぐため、ホスト名を限定
     *   - 5 秒タイムアウト、最大 256KB だけダウンロード
     */
    public function suggestTheme(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url' => 'required|string|max:500|url:http,https',
        ]);
        $url = $validated['url'];
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host || $this->isPrivateHost($host)) {
            return response()->json([
                'success'    => false,
                'message'    => 'プライベート IP やローカルホストは指定できません。',
                'suggested'  => null,
            ], 422);
        }

        try {
            $res = Http::timeout(5)
                ->withHeaders(['User-Agent' => 'KIDURI-Widget-Color-Sniffer/1.0'])
                ->withOptions(['allow_redirects' => ['max' => 3]])
                ->get($url);
            if (!$res->ok()) {
                return response()->json([
                    'success'   => false,
                    'message'   => "URL から HTTP {$res->status()} が返りました。",
                    'suggested' => null,
                ]);
            }
            // 大きすぎる HTML は先頭 256KB だけ見る
            $html = mb_substr($res->body(), 0, 262144);

            $themeColor = $this->extractMetaThemeColor($html);
            $title      = $this->extractTitle($html);

            // theme-color が無ければ favicon の URL だけ返す (FE で利用者に提示)
            $faviconUrl = $this->extractFaviconUrl($html, $url);

            return response()->json([
                'success' => true,
                'data' => [
                    'host'        => $host,
                    'title'       => $title,
                    'theme_color' => $themeColor,     // 例: "#14a898" or null
                    'favicon_url' => $faviconUrl,
                    // FE は theme_color を primary に流し込む
                    'suggested' => $themeColor ? [
                        'primary' => ltrim($themeColor, '#'),
                    ] : null,
                ],
            ]);
        } catch (ConnectionException $e) {
            return response()->json([
                'success'   => false,
                'message'   => 'URL に接続できませんでした。',
                'suggested' => null,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success'   => false,
                'message'   => '解析中にエラーが発生しました。',
                'suggested' => null,
            ]);
        }
    }

    private function extractMetaThemeColor(string $html): ?string
    {
        // <meta name="theme-color" content="#XXXXXX">
        if (preg_match('/<meta[^>]*name=["\']theme-color["\'][^>]*content=["\']([^"\']+)["\']/i', $html, $m)) {
            return trim($m[1]);
        }
        // 順序が逆のパターン: content="" 先 name="" 後
        if (preg_match('/<meta[^>]*content=["\']([^"\']+)["\'][^>]*name=["\']theme-color["\']/i', $html, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    private function extractTitle(string $html): ?string
    {
        if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html, $m)) {
            return trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        return null;
    }

    private function extractFaviconUrl(string $html, string $baseUrl): ?string
    {
        if (preg_match('/<link[^>]*rel=["\'](?:icon|shortcut icon|apple-touch-icon)["\'][^>]*href=["\']([^"\']+)["\']/i', $html, $m)) {
            $href = trim($m[1]);
            // 相対パスを base URL で吸収する
            if (preg_match('/^https?:\/\//i', $href)) return $href;
            $parsed = parse_url($baseUrl);
            $scheme = $parsed['scheme'] ?? 'https';
            $host   = $parsed['host'] ?? '';
            if ($host === '') return null;
            $port   = isset($parsed['port']) ? ':' . $parsed['port'] : '';
            if (str_starts_with($href, '//')) return $scheme . ':' . $href;
            if (str_starts_with($href, '/'))  return "{$scheme}://{$host}{$port}{$href}";
            $path = $parsed['path'] ?? '/';
            $dir  = rtrim(dirname($path), '/');
            return "{$scheme}://{$host}{$port}{$dir}/{$href}";
        }
        return null;
    }

    /**
     * SSRF 対策: ローカル/プライベート IP / 内部ドメインは弾く。
     */
    private function isPrivateHost(string $host): bool
    {
        if (in_array(strtolower($host), ['localhost', '127.0.0.1', '0.0.0.0', '::1'], true)) return true;
        if (preg_match('/\.local$/i', $host)) return true;
        if (preg_match('/^(10|192\.168|172\.(1[6-9]|2[0-9]|3[01]))\./', $host)) return true;
        if (preg_match('/^169\.254\./', $host)) return true; // link-local
        return false;
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
