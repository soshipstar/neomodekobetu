<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>{{ $newsletter->title ?? '施設通信' }} - PDF</title>
    <style>
        
        @page {
            size: A4 portrait;
            margin: 10mm;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'IPA Gothic', 'IPAGothic', 'Noto Sans JP', sans-serif;
            font-size: 10pt;
            line-height: 1.6;
            color: #333;
            print-color-adjust: exact;
            -webkit-print-color-adjust: exact;
        }

        /* ヘッダー */
        .header {
            text-align: center;
            padding-bottom: 10px;
            margin-bottom: 12px;
            border-bottom: 3px solid #6366f1;
        }

        .header-facility {
            font-size: 9pt;
            color: #6b7280;
            margin-bottom: 4px;
            letter-spacing: 2px;
        }

        .header-title {
            font-size: 18pt;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 4px;
        }

        .header-issue {
            font-size: 10pt;
            color: #6366f1;
            font-weight: bold;
        }

        .header-meta {
            font-size: 8pt;
            color: #9ca3af;
            margin-top: 6px;
        }

        /* あいさつ文 */
        .greeting-box {
            background: #f0f9ff;
            padding: 10px 14px;
            margin-bottom: 12px;
            border-left: 4px solid #6366f1;
        }

        .greeting-text {
            font-size: 9pt;
            line-height: 1.8;
            color: #374151;
        }

        /* セクション */
        .section {
            margin-bottom: 12px;
            page-break-inside: avoid;
        }

        .section-header {
            background: #6366f1;
            color: white;
            padding: 5px 12px;
            font-size: 11pt;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .section-content {
            padding: 0 8px;
            font-size: 9pt;
            line-height: 1.8;
            color: #374151;
        }

        /* カレンダーセクション */
        .calendar-box {
            background: #fafafa;
            border: 1px solid #e5e7eb;
            padding: 10px;
        }

        .calendar-content {
            font-size: 9pt;
            line-height: 1.6;
            color: #374151;
        }

        /* お知らせセクション */
        .notice-box {
            background: #fef3c7;
            border: 1px solid #fcd34d;
            padding: 8px 12px;
        }

        .notice-content {
            font-size: 9pt;
            line-height: 1.7;
            color: #92400e;
        }

        /* 学年別セクション用テーブル */
        .grade-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        .grade-table td {
            width: 50%;
            vertical-align: top;
            padding: 0;
            border: none;
        }

        .grade-table td:first-child {
            padding-right: 5px;
        }

        .grade-table td:last-child {
            padding-left: 5px;
        }

        .grade-section {
            background: #f9fafb;
            padding: 8px;
            border: 1px solid #e5e7eb;
        }

        .grade-header {
            font-size: 10pt;
            font-weight: bold;
            color: #6366f1;
            margin-bottom: 6px;
            padding-bottom: 4px;
            border-bottom: 2px solid #6366f1;
        }

        .grade-content {
            font-size: 9pt;
            line-height: 1.7;
            color: #374151;
        }

        /* 空のコンテンツ */
        .empty-warning {
            background: #fef2f2;
            border: 2px dashed #ef4444;
            padding: 30px;
            text-align: center;
            color: #dc2626;
        }

        .empty-warning h3 {
            font-size: 14pt;
            margin-bottom: 8px;
        }

        .empty-warning p {
            font-size: 10pt;
            color: #6b7280;
        }

        /* フッター */
        .footer {
            margin-top: 15px;
            padding-top: 8px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
        }

        .footer-text {
            font-size: 8pt;
            color: #9ca3af;
        }

        .footer-facility {
            font-size: 10pt;
            color: #6366f1;
            font-weight: bold;
            margin-top: 3px;
        }
    </style>
</head>
<body>
    @php
        $classroomName = $classroom->classroom_name ?? '施設';

        // マークダウン画像を <img> タグに変換
        $renderContent = function ($text) {
            if (!$text) return '';
            $escaped = e($text);
            // ![alt](url) → <img>
            $escaped = preg_replace(
                '/!\[([^\]]*)\]\(([^)]+)\)/',
                '<img src="$2" alt="$1" style="max-width:100%;height:auto;margin:4px 0;border-radius:4px;">',
                $escaped
            );
            return nl2br($escaped);
        };

        // セクションデータを構築
        $sections = [];
        if (!empty($newsletter->greeting)) {
            $sections[] = ['type' => 'greeting', 'content' => $newsletter->greeting];
        }
        if (!empty($newsletter->event_calendar)) {
            $sections[] = ['type' => 'calendar', 'title' => '今月の予定', 'content' => $newsletter->event_calendar];
        }
        if (!empty($newsletter->event_details)) {
            $sections[] = ['type' => 'normal', 'title' => 'イベント詳細', 'content' => $newsletter->event_details];
        }
        if (!empty($newsletter->weekly_reports)) {
            $sections[] = ['type' => 'normal', 'title' => '活動の様子', 'content' => $newsletter->weekly_reports];
        }
        if (!empty($newsletter->weekly_intro)) {
            $sections[] = ['type' => 'normal', 'title' => '週間活動紹介', 'content' => $newsletter->weekly_intro];
        }
        if (!empty($newsletter->event_results)) {
            $sections[] = ['type' => 'normal', 'title' => 'イベント結果報告', 'content' => $newsletter->event_results];
        }
        if (!empty($newsletter->requests)) {
            $sections[] = ['type' => 'notice', 'title' => '施設からのお願い', 'content' => $newsletter->requests];
        }
        if (!empty($newsletter->others)) {
            $sections[] = ['type' => 'notice', 'title' => 'その他のお知らせ', 'content' => $newsletter->others];
        }

        $hasContent = count($sections) > 0;

        // 学年別セクション
        $hasElementary = !empty($newsletter->elementary_report);
        $hasJunior = !empty($newsletter->junior_report);
    @endphp

    {{-- ヘッダー --}}
    <div class="header">
        <div class="header-facility">{{ $classroomName }}</div>
        <div class="header-title">{{ $newsletter->title ?? '' }}</div>
        <div class="header-issue">{{ $newsletter->year }}年{{ $newsletter->month }}月号</div>
    </div>

    @if (!$hasContent && !$hasElementary && !$hasJunior)
        {{-- コンテンツがない場合 --}}
        <div class="empty-warning">
            <h3>コンテンツがありません</h3>
            <p>この通信にはまだ内容が入力されていません。</p>
        </div>
    @else
        {{-- あいさつ文 --}}
        @foreach ($sections as $section)
            @if ($section['type'] === 'greeting')
                <div class="greeting-box">
                    <div class="greeting-text">{!! $renderContent($section['content']) !!}</div>
                </div>
            @endif
        @endforeach

        {{-- 通常セクション --}}
        @foreach ($sections as $section)
            @if ($section['type'] === 'calendar')
                <div class="section">
                    <div class="section-header">{{ $section['title'] }}</div>
                    <div class="calendar-box">
                        <div class="calendar-content">{!! $renderContent($section['content']) !!}</div>
                    </div>
                </div>
            @elseif ($section['type'] === 'normal')
                <div class="section">
                    <div class="section-header">{{ $section['title'] }}</div>
                    <div class="section-content">{!! $renderContent($section['content']) !!}</div>
                </div>
            @elseif ($section['type'] === 'notice')
                <div class="section">
                    <div class="section-header">{{ $section['title'] }}</div>
                    <div class="notice-box">
                        <div class="notice-content">{!! $renderContent($section['content']) !!}</div>
                    </div>
                </div>
            @endif
        @endforeach

        {{-- 学年別セクション（2カラム） --}}
        @if ($hasElementary || $hasJunior)
            <table class="grade-table">
                <tr>
                    @if ($hasElementary)
                    <td>
                        <div class="grade-section">
                            <div class="grade-header">小学生の活動</div>
                            <div class="grade-content">{!! $renderContent($newsletter->elementary_report) !!}</div>
                        </div>
                    </td>
                    @endif
                    @if ($hasJunior)
                    <td>
                        <div class="grade-section">
                            <div class="grade-header">中高生の活動</div>
                            <div class="grade-content">{!! $renderContent($newsletter->junior_report) !!}</div>
                        </div>
                    </td>
                    @endif
                    @if ($hasElementary && !$hasJunior)
                    <td></td>
                    @elseif (!$hasElementary && $hasJunior)
                    <td></td>
                    @endif
                </tr>
            </table>
        @endif
    @endif

    {{-- フッター --}}
    <div class="footer">
        <div class="footer-text">
            @if ($newsletter->published_at)
                発行日: {{ $newsletter->published_at->format('Y年n月j日') }}
            @else
                ※ この通信は下書き状態です
            @endif
        </div>
        <div class="footer-facility">{{ $classroomName }}</div>
    </div>
</body>
</html>
