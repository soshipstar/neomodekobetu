<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsletterSettingController extends Controller
{
    /**
     * 施設通信設定を取得
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroomId = $user->classroom_id;

        $setting = NewsletterSetting::where('classroom_id', $classroomId)->first();

        if (!$setting) {
            // デフォルト設定を返す
            return response()->json([
                'success' => true,
                'data'    => [
                    'display_settings' => [
                        'show_greeting' => true,
                        'show_event_calendar' => true,
                        'show_event_details' => true,
                        'show_weekly_reports' => true,
                        'show_weekly_intro' => false,
                        'show_event_results' => false,
                        'show_requests' => true,
                        'show_others' => true,
                        'show_elementary_report' => false,
                        'show_junior_report' => false,
                        'show_custom_section' => false,
                        'show_facility_name' => true,
                        'show_logo' => false,
                    ],
                    'calendar_format' => 'list',
                    'ai_instructions' => [],
                    'custom_sections' => [],
                    'default_requests' => '',
                    'default_others' => '',
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data'    => $setting,
        ]);
    }

    /**
     * 施設通信設定を保存
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroomId = $user->classroom_id;

        $validated = $request->validate([
            'display_settings'   => 'nullable|array',
            'calendar_format'    => 'nullable|string|max:50',
            'ai_instructions'    => 'nullable',
            'custom_sections'    => 'nullable',
            'default_requests'   => 'nullable|string',
            'default_others'     => 'nullable|string',
        ]);

        $setting = NewsletterSetting::updateOrCreate(
            ['classroom_id' => $classroomId],
            $validated
        );

        return response()->json([
            'success' => true,
            'data'    => $setting,
            'message' => '設定を保存しました。',
        ]);
    }
}
