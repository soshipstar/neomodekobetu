<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettingController extends Controller
{
    /**
     * システム設定一覧を取得
     */
    public function index(): JsonResponse
    {
        $settings = DB::table('system_settings')
            ->orderBy('setting_key')
            ->get()
            ->keyBy('setting_key')
            ->map(fn ($s) => $s->setting_value);

        return response()->json([
            'success' => true,
            'data'    => $settings,
        ]);
    }

    /**
     * システム設定を更新
     */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'settings' => 'required|array',
        ]);

        DB::transaction(function () use ($request) {
            foreach ($request->settings as $key => $value) {
                DB::table('system_settings')->updateOrInsert(
                    ['setting_key' => $key],
                    [
                        'setting_value' => is_array($value) ? json_encode($value) : (string) $value,
                        'updated_at'    => now(),
                    ]
                );
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'システム設定を更新しました。',
        ]);
    }
}
