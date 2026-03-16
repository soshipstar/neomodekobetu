<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>支援案 - {{ $plan->activity_name }}</title>
    <style>
        @font-face { font-family: "ipag"; src: url("file:///var/www/html/storage/fonts/ipag.ttf"); font-weight: normal; font-style: normal; }
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
            font-family: ipag, 'IPA Gothic', 'IPAGothic', sans-serif;
            font-size: 9pt;
            line-height: 1.4;
            color: #333;
        }

        .header {
            text-align: center;
            margin-bottom: 10px;
            border-bottom: 2px solid #333;
            padding-bottom: 8px;
        }

        .header h1 {
            font-size: 16pt;
            margin: 0;
        }

        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .meta-table td {
            padding: 3px 6px;
            font-size: 9pt;
            border: 1px solid #ccc;
        }

        .meta-label {
            font-weight: bold;
            background: #f0f0f0;
            width: 20%;
        }

        .section-title {
            background: #4a5568;
            color: white;
            padding: 4px 8px;
            font-weight: bold;
            font-size: 10pt;
            margin-bottom: 5px;
            margin-top: 10px;
        }

        .section-content {
            padding: 6px 8px;
            border: 1px solid #999;
            min-height: 30px;
            margin-bottom: 10px;
            white-space: pre-wrap;
            word-wrap: break-word;
            overflow-wrap: break-word;
            font-size: 8pt;
            line-height: 1.4;
            max-width: 100%;
            overflow: hidden;
        }

        .schedule-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            font-size: 8pt;
        }

        .schedule-table th,
        .schedule-table td {
            border: 1px solid #333;
            padding: 3px 5px;
            text-align: left;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
            font-size: 7.5pt;
        }

        .schedule-table th {
            background: #e2e8f0;
            font-weight: bold;
            text-align: center;
            font-size: 8pt;
        }

        .tag-list {
            margin-bottom: 10px;
        }

        .tag {
            display: inline-block;
            background: #e2e8f0;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 8pt;
            margin-right: 4px;
            margin-bottom: 2px;
        }

        .footer {
            text-align: center;
            margin-top: 15px;
            font-size: 7pt;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>活動支援案</h1>
    </div>

    {{-- メタ情報 --}}
    <table class="meta-table">
        <tr>
            <td class="meta-label">活動名</td>
            <td>{{ $plan->activity_name }}</td>
            <td class="meta-label">活動日</td>
            <td>{{ $plan->activity_date ? $plan->activity_date->format('Y年m月d日') : '' }}</td>
        </tr>
        <tr>
            <td class="meta-label">種別</td>
            <td>{{ $planTypeLabel }}</td>
            <td class="meta-label">対象学年</td>
            <td>{{ $targetGradeLabel }}</td>
        </tr>
        <tr>
            <td class="meta-label">総活動時間</td>
            <td>{{ $plan->total_duration }}分</td>
            <td class="meta-label">曜日</td>
            <td>{{ $dayOfWeekLabel }}</td>
        </tr>
        <tr>
            <td class="meta-label">作成者</td>
            <td colspan="3">{{ $plan->staff->full_name ?? '' }}</td>
        </tr>
    </table>

    {{-- タグ --}}
    @if ($plan->tags)
    <div class="tag-list">
        <strong>タグ：</strong>
        @foreach (explode(',', $plan->tags) as $tag)
            <span class="tag">{{ trim($tag) }}</span>
        @endforeach
    </div>
    @endif

    {{-- 活動の目的 --}}
    @if ($plan->activity_purpose)
    <div class="section-title">活動の目的</div>
    <div class="section-content">{!! nl2br(e($plan->activity_purpose)) !!}</div>
    @endif

    {{-- 活動の内容 --}}
    @if ($plan->activity_content)
    <div class="section-title">活動の内容</div>
    <div class="section-content">{!! nl2br(e($plan->activity_content)) !!}</div>
    @endif

    {{-- 五領域への配慮 --}}
    @if ($plan->five_domains_consideration)
    <div class="section-title">五領域への配慮</div>
    <div class="section-content">{!! nl2br(e($plan->five_domains_consideration)) !!}</div>
    @endif

    {{-- 活動スケジュール --}}
    @if ($plan->activity_schedule && count($plan->activity_schedule) > 0)
    <div class="section-title">活動スケジュール</div>
    <table class="schedule-table">
        <thead>
            <tr>
                <th style="width: 5%;">No.</th>
                <th style="width: 12%;">種別</th>
                <th style="width: 25%;">活動名</th>
                <th style="width: 10%;">時間</th>
                <th style="width: 48%;">内容</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($plan->activity_schedule as $i => $item)
            <tr>
                <td style="text-align: center;">{{ $i + 1 }}</td>
                <td style="text-align: center;">{{ ($item['type'] ?? '') === 'routine' ? '毎日の支援' : '主活動' }}</td>
                <td>{{ $item['name'] ?? '' }}</td>
                <td style="text-align: center;">{{ $item['duration'] ?? '' }}分</td>
                <td>{!! nl2br(e($item['content'] ?? '')) !!}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    {{-- その他 --}}
    @if ($plan->other_notes)
    <div class="section-title">その他の注意点</div>
    <div class="section-content">{!! nl2br(e($plan->other_notes)) !!}</div>
    @endif

    <div class="footer">
        出力日時: {{ now()->format('Y年m月d日 H:i') }}
    </div>
</body>
</html>
