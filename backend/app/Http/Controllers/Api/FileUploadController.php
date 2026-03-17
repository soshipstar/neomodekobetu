<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileUploadController extends Controller
{
    /**
     * 許可するMIMEタイプ
     */
    private const ALLOWED_MIMES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/csv', 'text/plain',
    ];

    /**
     * ファイルをアップロード
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file'     => 'required|file|max:10240', // 10MB
            'category' => 'nullable|string|in:chat,support_plan,newsletter,general',
        ]);

        $file = $request->file('file');

        // MIMEタイプチェック
        if (! in_array($file->getMimeType(), self::ALLOWED_MIMES)) {
            return response()->json([
                'success' => false,
                'message' => 'このファイル形式はアップロードできません。',
            ], 422);
        }

        $category = $request->input('category', 'general');
        $directory = "uploads/{$category}";

        $filename = Str::ulid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs($directory, $filename, 'public');

        return response()->json([
            'success' => true,
            'data'    => [
                'path'          => $path,
                'url'           => Storage::disk('public')->url($path),
                'original_name' => $file->getClientOriginalName(),
                'size'          => $file->getSize(),
                'mime_type'     => $file->getMimeType(),
            ],
        ], 201);
    }

    /**
     * ファイルをダウンロード
     */
    public function download(Request $request, string $path)
    {
        // パスのトラバーサル攻撃を防止
        $path = ltrim($path, '/');
        if (str_contains($path, '..')) {
            return response()->json([
                'success' => false,
                'message' => '不正なパスです。',
            ], 400);
        }

        if (! Storage::disk('public')->exists($path)) {
            return response()->json([
                'success' => false,
                'message' => 'ファイルが見つかりません。',
            ], 404);
        }

        return Storage::disk('public')->download($path);
    }

    /**
     * ファイルを削除
     */
    public function destroy(Request $request, string $file): JsonResponse
    {
        // file パラメータはBase64エンコードされたパス
        $path = base64_decode($file);

        if (! $path || ! Storage::disk('public')->exists($path)) {
            return response()->json([
                'success' => false,
                'message' => 'ファイルが見つかりません。',
            ], 404);
        }

        Storage::disk('public')->delete($path);

        return response()->json([
            'success' => true,
            'message' => 'ファイルを削除しました。',
        ]);
    }
}
