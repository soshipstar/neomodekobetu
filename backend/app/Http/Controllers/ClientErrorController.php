<?php

namespace App\Http\Controllers;

use App\Models\ErrorLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientErrorController extends Controller
{
    /**
     * クライアント（フロントエンド）で発生した未捕捉エラーを error_logs に記録する。
     * 認証不要（ログイン前画面でも落ちる想定）のため、内容長は必ず切り詰める。
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message'   => 'nullable|string',
            'name'      => 'nullable|string',
            'stack'     => 'nullable|string',
            'digest'    => 'nullable|string',
            'url'       => 'nullable|string',
            'userAgent' => 'nullable|string',
            'timestamp' => 'nullable|string',
        ]);

        $cut = static fn (?string $v, int $max): ?string => $v === null ? null : mb_substr($v, 0, $max);

        ErrorLog::create([
            'level'           => 'error',
            'message'         => $cut($data['message'] ?? '(no message)', 2000),
            'exception_class' => $cut($data['name'] ?? 'ClientError', 255),
            'file'            => null,
            'line'            => null,
            'trace'           => $data['stack'] ?? null ? ['stack' => $cut($data['stack'], 8000), 'digest' => $data['digest'] ?? null] : null,
            'url'             => $cut($data['url'] ?? null, 2000),
            'method'          => 'CLIENT',
            'user_id'         => optional($request->user())->id,
            'ip_address'      => $request->ip(),
            'user_agent'      => $cut($data['userAgent'] ?? $request->userAgent(), 500),
            'request_data'    => ['client_timestamp' => $data['timestamp'] ?? null],
            'is_resolved'     => false,
        ]);

        return response()->json(['success' => true]);
    }
}
