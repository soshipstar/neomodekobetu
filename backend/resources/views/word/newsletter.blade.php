<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:w="urn:schemas-microsoft-com:office:word"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <!--[if gte mso 9]>
    <xml>
        <w:WordDocument>
            <w:View>Print</w:View>
            <w:Zoom>100</w:Zoom>
        </w:WordDocument>
    </xml>
    <![endif]-->
    <style>
        body { font-family: 'ＭＳ ゴシック', 'MS Gothic', sans-serif; font-size: 10pt; line-height: 1.6; color: #333; }
        .header { text-align: center; padding-bottom: 8px; margin-bottom: 12px; border-bottom: 3px solid #6366f1; }
        .header-facility { font-size: 9pt; color: #6b7280; margin-bottom: 4px; }
        .header-title { font-size: 18pt; font-weight: bold; color: #1f2937; margin-bottom: 4px; }
        .header-issue { font-size: 10pt; color: #6366f1; font-weight: bold; }
        .greeting-box { background: #f0f9ff; padding: 10px 14px; margin-bottom: 12px; border-left: 4px solid #6366f1; }
        .greeting-text { font-size: 9pt; line-height: 1.8; color: #374151; }
        .section { margin-bottom: 12px; }
        .section-header { background: #6366f1; color: white; padding: 5px 12px; font-size: 11pt; font-weight: bold; margin-bottom: 8px; }
        .section-content { padding: 0 8px; font-size: 9pt; line-height: 1.8; color: #374151; }
        .calendar-box { background: #fafafa; border: 1px solid #e5e7eb; padding: 10px; }
        .notice-box { background: #fef3c7; border: 1px solid #fcd34d; padding: 8px 12px; }
        .notice-content { font-size: 9pt; line-height: 1.7; color: #92400e; }
        .grade-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .grade-table td { width: 50%; vertical-align: top; padding: 4px; }
        .grade-section { background: #f9fafb; padding: 8px; border: 1px solid #e5e7eb; }
        .grade-header { font-size: 10pt; font-weight: bold; color: #6366f1; margin-bottom: 6px; padding-bottom: 4px; border-bottom: 2px solid #6366f1; }
        .grade-content { font-size: 9pt; line-height: 1.7; color: #374151; }
        .footer { margin-top: 15px; padding-top: 8px; border-top: 1px solid #e5e7eb; text-align: center; }
        .footer-text { font-size: 8pt; color: #9ca3af; }
        .footer-facility { font-size: 10pt; color: #6366f1; font-weight: bold; margin-top: 3px; }
        img { max-width: 200px; height: auto; }
    </style>
</head>
<body>
@php
    $classroomName = $classroom->classroom_name ?? '施設';
    $renderContent = function ($text) {
        if (!$text) return '';
        $images = [];
        $text = preg_replace_callback('/!\[([^\]]*)\]\(([^)]+)\)/', function ($m) use (&$images) {
            $key = '{{IMG_' . count($images) . '}}';
            $images[$key] = '<img src="' . htmlspecialchars($m[2], ENT_QUOTES) . '" alt="' . htmlspecialchars($m[1], ENT_QUOTES) . '" style="max-width:200px;height:auto;">';
            return $key;
        }, $text);
        $escaped = e($text);
        foreach ($images as $key => $img) {
            $escaped = str_replace(e($key), $img, $escaped);
        }
        return nl2br($escaped);
    };
@endphp

<div class="header">
    <div class="header-facility">{{ $classroomName }}</div>
    <div class="header-title">{{ $newsletter->title ?? '' }}</div>
    <div class="header-issue">{{ $newsletter->year }}年{{ $newsletter->month }}月号</div>
</div>

@if (!empty($newsletter->greeting))
<div class="greeting-box">
    <div class="greeting-text">{!! $renderContent($newsletter->greeting) !!}</div>
</div>
@endif

@if (!empty($newsletter->event_calendar))
<div class="section">
    <div class="section-header">今月の予定</div>
    <div class="calendar-box">{!! $renderContent($newsletter->event_calendar) !!}</div>
</div>
@endif

@if (!empty($newsletter->event_details))
<div class="section">
    <div class="section-header">イベント詳細</div>
    <div class="section-content">{!! $renderContent($newsletter->event_details) !!}</div>
</div>
@endif

@if (!empty($newsletter->weekly_reports))
<div class="section">
    <div class="section-header">活動の様子</div>
    <div class="section-content">{!! $renderContent($newsletter->weekly_reports) !!}</div>
</div>
@endif

@if (!empty($newsletter->weekly_intro))
<div class="section">
    <div class="section-header">週間活動紹介</div>
    <div class="section-content">{!! $renderContent($newsletter->weekly_intro) !!}</div>
</div>
@endif

@if (!empty($newsletter->event_results))
<div class="section">
    <div class="section-header">イベント結果報告</div>
    <div class="section-content">{!! $renderContent($newsletter->event_results) !!}</div>
</div>
@endif

@if (!empty($newsletter->elementary_report) || !empty($newsletter->junior_report))
<table class="grade-table">
    <tr>
        @if (!empty($newsletter->elementary_report))
        <td>
            <div class="grade-section">
                <div class="grade-header">小学生の活動</div>
                <div class="grade-content">{!! $renderContent($newsletter->elementary_report) !!}</div>
            </div>
        </td>
        @endif
        @if (!empty($newsletter->junior_report))
        <td>
            <div class="grade-section">
                <div class="grade-header">中高生の活動</div>
                <div class="grade-content">{!! $renderContent($newsletter->junior_report) !!}</div>
            </div>
        </td>
        @endif
    </tr>
</table>
@endif

@if (!empty($newsletter->requests))
<div class="section">
    <div class="section-header">施設からのお願い</div>
    <div class="notice-box">
        <div class="notice-content">{!! $renderContent($newsletter->requests) !!}</div>
    </div>
</div>
@endif

@if (!empty($newsletter->others))
<div class="section">
    <div class="section-header">その他のお知らせ</div>
    <div class="notice-box">
        <div class="notice-content">{!! $renderContent($newsletter->others) !!}</div>
    </div>
</div>
@endif

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
