<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Newsletter;
use App\Services\PuppeteerPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenAI\Laravel\Facades\OpenAI;

class NewsletterController extends Controller
{
    /**
     * お便り一覧を取得
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Newsletter::with('creator:id,full_name');

        if ($user->classroom_id) {
            $query->where('classroom_id', $user->classroom_id);
        }

        if ($request->filled('is_published')) {
            $query->where('is_published', $request->boolean('is_published'));
        }

        if ($request->filled('year')) {
            $query->where('year', $request->year);
        }

        $newsletters = $query->orderByDesc('year')
            ->orderByDesc('month')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'data'    => $newsletters,
        ]);
    }

    /**
     * お便りを新規作成
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year'              => 'required|integer|min:2020|max:2100',
            'month'             => 'required|integer|min:1|max:12',
            'title'             => 'required|string|max:255',
            'greeting'          => 'nullable|string',
            'event_calendar'    => 'nullable|string',
            'event_details'     => 'nullable|string',
            'weekly_reports'    => 'nullable|string',
            'weekly_intro'      => 'nullable|string',
            'event_results'     => 'nullable|string',
            'requests'          => 'nullable|string',
            'others'            => 'nullable|string',
            'elementary_report' => 'nullable|string',
            'junior_report'     => 'nullable|string',
        ]);

        $newsletter = Newsletter::create(array_merge($validated, [
            'classroom_id' => $request->user()->classroom_id,
            'created_by'   => $request->user()->id,
            'is_published' => false,
        ]));

        return response()->json([
            'success' => true,
            'data'    => $newsletter,
            'message' => 'お便りを作成しました。',
        ], 201);
    }

    /**
     * お便り詳細を取得
     */
    public function show(Newsletter $newsletter): JsonResponse
    {
        $newsletter->load('creator:id,full_name');

        return response()->json([
            'success' => true,
            'data'    => $newsletter,
        ]);
    }

    /**
     * お便りを更新（下書き保存）
     */
    public function update(Request $request, Newsletter $newsletter): JsonResponse
    {
        $validated = $request->validate([
            'title'             => 'sometimes|required|string|max:255',
            'greeting'          => 'nullable|string',
            'event_calendar'    => 'nullable|string',
            'event_details'     => 'nullable|string',
            'weekly_reports'    => 'nullable|string',
            'weekly_intro'      => 'nullable|string',
            'event_results'     => 'nullable|string',
            'requests'          => 'nullable|string',
            'others'            => 'nullable|string',
            'elementary_report' => 'nullable|string',
            'junior_report'     => 'nullable|string',
        ]);

        $newsletter->update($validated);

        return response()->json([
            'success' => true,
            'data'    => $newsletter->fresh(),
            'message' => '下書きを保存しました。',
        ]);
    }

    /**
     * お便りを削除
     */
    public function destroy(Newsletter $newsletter): JsonResponse
    {
        if ($newsletter->is_published) {
            return response()->json([
                'success' => false,
                'message' => '公開済みのお便りは削除できません。',
            ], 422);
        }

        $newsletter->delete();

        return response()->json([
            'success' => true,
            'message' => 'お便りを削除しました。',
        ]);
    }

    /**
     * AI でお便り内容を生成
     */
    public function generateAi(Request $request, Newsletter $newsletter): JsonResponse
    {
        $request->validate([
            'section' => 'required|string|in:greeting,event_details,weekly_reports,event_results,others',
            'context' => 'nullable|string',
        ]);

        $section = $request->section;
        $context = $request->context ?? '';

        $sectionLabels = [
            'greeting'       => 'あいさつ文',
            'event_details'  => '行事の詳細',
            'weekly_reports' => '週報',
            'event_results'  => '行事の結果報告',
            'others'         => 'その他のお知らせ',
        ];

        $sectionLabel = $sectionLabels[$section] ?? $section;

        try {
            $apiKey = config("services.openai.api_key", env("OPENAI_API_KEY")); $client = OpenAI::client($apiKey); $response = $client->chat()->create([
                'model'    => 'gpt-4o',
                'messages' => [
                    [
                        'role'    => 'system',
                        'content' => '児童発達支援施設のスタッフとして、保護者向けのお便りの文章を作成します。温かみがあり、丁寧な表現を心がけてください。',
                    ],
                    [
                        'role'    => 'user',
                        'content' => "{$newsletter->year}年{$newsletter->month}月号のお便り「{$newsletter->title}」の"
                            . "「{$sectionLabel}」セクションの文章を生成してください。\n\n"
                            . ($context ? "参考情報：{$context}\n\n" : '')
                            . "適切な文章を生成してください。HTMLタグは使わず、プレーンテキストで出力してください。",
                    ],
                ],
                'temperature'           => 0.7,
                'max_completion_tokens' => 1000,
            ]);

            $generatedText = $response->choices[0]->message->content;

            return response()->json([
                'success' => true,
                'data'    => [
                    'section' => $section,
                    'content' => $generatedText,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'AI生成中にエラーが発生しました: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * お便りを発行（公開）
     */
    public function publish(Request $request, Newsletter $newsletter): JsonResponse
    {
        if (empty($newsletter->title) || empty($newsletter->greeting)) {
            return response()->json([
                'success' => false,
                'message' => 'タイトルとあいさつ文は必須です。',
            ], 422);
        }

        $newsletter->update([
            'is_published' => true,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $newsletter,
            'message' => '通信を発行しました。',
        ]);
    }

    /**
     * お便り PDF をダウンロード
     */
    public function pdf(Request $request, Newsletter $newsletter)
    {
        $newsletter->load(['classroom', 'creator:id,full_name']);

        $filename = 'newsletter_' . $newsletter->year . '_' . $newsletter->month . '_' . ($newsletter->title ?? $newsletter->id) . '.pdf';

        return PuppeteerPdfService::download('pdf.newsletter', [
            'newsletter' => $newsletter,
            'classroom'  => $newsletter->classroom,
        ], $filename);
    }
}
