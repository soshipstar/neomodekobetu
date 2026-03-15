<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>週間計画表 - {{ $weekStartFormatted }}〜{{ $weekEndFormatted }}</title>
    <style>
        @font-face { font-family: "ipag"; src: url("file:///var/www/html/storage/fonts/ipag.ttf"); font-weight: normal; font-style: normal; }
        @page {
            size: A4 portrait;
            margin: 8mm;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: ipag, 'IPA Gothic', 'IPAGothic', sans-serif;
            font-size: 9pt;
            line-height: 1.3;
            color: #333;
        }

        .header {
            text-align: center;
            margin-bottom: 8px;
            border-bottom: 2px solid #333;
            padding-bottom: 6px;
        }

        .header h1 {
            font-size: 14pt;
            margin: 0 0 4px 0;
        }

        .header-info {
            width: 100%;
            border-collapse: collapse;
        }

        .header-info td {
            font-size: 9pt;
            padding: 0 5px;
        }

        /* 目標セクション */
        .goal-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }

        .goal-table th,
        .goal-table td {
            border: 1px solid #333;
            padding: 3px 6px;
            font-size: 9pt;
            vertical-align: top;
        }

        .goal-table th {
            background: #4a90d9;
            color: white;
            font-weight: bold;
            text-align: left;
            width: 18%;
        }

        .goal-content {
            min-height: 18px;
        }

        .eval-header {
            background: #e2e8f0;
            text-align: center;
            width: 45px;
            font-size: 7pt;
        }

        .eval-cell {
            text-align: center;
            width: 45px;
        }

        /* 三分割目標 */
        .three-goals {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }

        .three-goals th,
        .three-goals td {
            border: 1px solid #333;
            padding: 3px 5px;
            font-size: 8pt;
            vertical-align: top;
        }

        .three-goals th {
            background: #4a90d9;
            color: white;
            font-weight: bold;
            text-align: center;
            font-size: 8pt;
        }

        .three-goals td {
            width: 33.33%;
            min-height: 20px;
        }

        /* 日別計画 */
        .daily-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }

        .daily-table th,
        .daily-table td {
            border: 1px solid #333;
            padding: 3px 5px;
            font-size: 8pt;
            vertical-align: top;
        }

        .daily-table th {
            background: #4a90d9;
            color: white;
            font-weight: bold;
            text-align: center;
        }

        .daily-table .day-header {
            background: #e8e8e8;
            text-align: center;
            font-weight: bold;
            width: 50px;
        }

        .daily-table .day-date {
            font-size: 7pt;
            font-weight: normal;
        }

        .daily-table .day-content {
            min-height: 15px;
        }

        .daily-table .day-eval {
            text-align: center;
            width: 45px;
        }

        /* 評価凡例 */
        .legend {
            margin-top: 4px;
            margin-bottom: 6px;
            padding: 3px 6px;
            background: #f5f5f5;
            border: 1px solid #ccc;
            font-size: 7pt;
        }

        .legend-inline {
            display: inline;
        }

        /* 保護者欄 */
        .parent-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }

        .parent-table th,
        .parent-table td {
            border: 2px solid #333;
            padding: 4px 6px;
            vertical-align: top;
        }

        .parent-table th {
            background: #ffeb3b;
            font-weight: bold;
            font-size: 10pt;
            text-align: left;
        }

        .parent-comment {
            min-height: 40px;
            font-size: 8pt;
            color: #666;
        }

        .parent-sign {
            width: 100px;
            text-align: center;
            font-size: 8pt;
        }

        .parent-sign-box {
            border: 1px solid #333;
            height: 30px;
            margin-top: 3px;
        }

        /* コメント欄 */
        .comment-section {
            margin-top: 8px;
        }

        .section-title {
            background: #4a5568;
            color: white;
            padding: 3px 8px;
            font-weight: bold;
            font-size: 9pt;
            margin-bottom: 4px;
        }

        .section-content {
            padding: 5px 8px;
            border: 1px solid #999;
            min-height: 25px;
            margin-bottom: 8px;
            white-space: pre-wrap;
            font-size: 8pt;
        }

        .footer {
            text-align: center;
            margin-top: 8px;
            font-size: 7pt;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>週間計画表</h1>
        <table class="header-info">
            <tr>
                <td style="text-align: left;">作成者：{{ $plan->creator->full_name ?? '' }}</td>
                <td style="text-align: center;">期間：{{ $weekStartFormatted }}（月）〜 {{ $weekEndFormatted }}（日）</td>
                <td style="text-align: right;">提出日：{{ $submitFormatted }}（月）</td>
            </tr>
        </table>
    </div>

    {{-- 今週の目標 --}}
    <table class="goal-table">
        <tr>
            <th colspan="2">今週の目標</th>
            <td class="eval-header">評価</td>
        </tr>
        <tr>
            <td colspan="2" class="goal-content">{!! nl2br(e($content['weekly_goal'] ?? '')) !!}</td>
            <td class="eval-cell"></td>
        </tr>
    </table>

    {{-- いっしょに決めた目標 --}}
    <table class="goal-table">
        <tr>
            <th colspan="2">いっしょに決めた目標</th>
            <td class="eval-header">評価</td>
        </tr>
        <tr>
            <td colspan="2" class="goal-content">{!! nl2br(e($content['shared_goal'] ?? '')) !!}</td>
            <td class="eval-cell"></td>
        </tr>
    </table>

    {{-- やるべきこと・やったほうがいいこと・やりたいこと --}}
    <table class="three-goals">
        <tr>
            <th>やるべきこと</th>
            <th>やったほうがいいこと</th>
            <th>やりたいこと</th>
        </tr>
        <tr>
            <td>{!! nl2br(e($content['must_do'] ?? '')) !!}</td>
            <td>{!! nl2br(e($content['should_do'] ?? '')) !!}</td>
            <td>{!! nl2br(e($content['want_to_do'] ?? '')) !!}</td>
        </tr>
    </table>

    {{-- 各曜日の計画 --}}
    <table class="daily-table">
        <tr>
            <th colspan="2">各曜日の計画・目標</th>
            <th style="width: 45px;">評価</th>
        </tr>
        @php
            $days = ['月', '火', '水', '木', '金', '土', '日'];
        @endphp
        @foreach ($days as $index => $day)
        @php
            $dayKey = "day_{$index}";
            $dayDate = $plan->week_start_date->copy()->addDays($index)->format('n/j');
            $dayContent = $content[$dayKey] ?? '';
        @endphp
        <tr>
            <td class="day-header">
                {{ $day }}<br><span class="day-date">({{ $dayDate }})</span>
            </td>
            <td class="day-content">{!! nl2br(e($dayContent)) !!}</td>
            <td class="day-eval"></td>
        </tr>
        @endforeach
    </table>

    {{-- 評価凡例 --}}
    <div class="legend">
        【評価の書き方】 できた度合いを記入してください &nbsp;&nbsp;
        1=できなかった &nbsp; 2=あまりできなかった &nbsp; 3=まあまあ &nbsp; 4=できた &nbsp; 5=よくできた
    </div>

    {{-- 全体コメント --}}
    @if (!empty($content['overall_comment']))
    <div class="comment-section">
        <div class="section-title">全体コメント</div>
        <div class="section-content">{!! nl2br(e($content['overall_comment'])) !!}</div>
    </div>
    @endif

    {{-- 提出物 --}}
    @if ($submissions && $submissions->count() > 0)
    <div class="comment-section">
        <div class="section-title">提出物</div>
        <div class="section-content">
            @foreach ($submissions as $sub)
                ・{{ $sub->title ?? $sub->description ?? '提出物' }}
                @if ($sub->submitted_at)
                    （提出済: {{ $sub->submitted_at->format('m/d') }}）
                @else
                    （未提出）
                @endif
                <br>
            @endforeach
        </div>
    </div>
    @endif

    {{-- 保護者欄 --}}
    <table class="parent-table">
        <tr>
            <th colspan="2">おうちの方へ（一週間後にご記入ください）</th>
        </tr>
        <tr>
            <td>
                <div class="parent-comment">お子様の様子やコメントをご記入ください</div>
            </td>
            <td class="parent-sign">
                確認印・サイン
                <div class="parent-sign-box"></div>
            </td>
        </tr>
    </table>

    <div class="footer">
        出力日時: {{ now()->format('Y年m月d日 H:i') }}
    </div>
</body>
</html>
