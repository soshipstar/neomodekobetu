<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>支援案 - {{ $plan->activity_name }}</title>
    <style>
        @font-face { font-family: "ipag"; src: url("file:///var/www/html/storage/fonts/ipag.ttf"); font-weight: normal; font-style: normal; }
        @page {
            size: A4 portrait;
            margin: 15mm 18mm;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: ipag, 'IPA Gothic', 'IPAGothic', sans-serif;
            font-size: 8.5pt;
            line-height: 1.35;
            color: #222;
        }

        /* ヘッダー */
        .header {
            text-align: center;
            margin-bottom: 8px;
            padding-bottom: 6px;
            border-bottom: 2.5px solid #2c3e50;
        }

        .header h1 {
            font-size: 14pt;
            letter-spacing: 2pt;
            color: #2c3e50;
            margin: 0;
        }

        .header-sub {
            font-size: 7pt;
            color: #777;
            margin-top: 2px;
        }

        /* メタ情報テーブル */
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }

        .meta-table td {
            padding: 2.5px 6px;
            font-size: 8pt;
            border: 0.5px solid #aaa;
            line-height: 1.3;
        }

        .meta-label {
            font-weight: bold;
            background: #f5f6f8;
            width: 18%;
            color: #444;
        }

        /* セクション */
        .section {
            margin-bottom: 6px;
        }

        .section-title {
            background: #34495e;
            color: white;
            padding: 3px 8px;
            font-weight: bold;
            font-size: 8.5pt;
            margin-bottom: 0;
            letter-spacing: 0.5pt;
        }

        .section-content {
            padding: 4px 8px;
            border: 0.5px solid #aaa;
            border-top: none;
            font-size: 8pt;
            line-height: 1.3;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .section-content p {
            margin: 0;
            padding: 0;
        }

        /* スケジュールテーブル */
        .schedule-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }

        .schedule-table th,
        .schedule-table td {
            border: 0.5px solid #888;
            padding: 2px 4px;
            text-align: left;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
            font-size: 7.5pt;
            line-height: 1.25;
        }

        .schedule-table th {
            background: #ecf0f1;
            font-weight: bold;
            text-align: center;
            font-size: 7.5pt;
            color: #333;
        }

        .schedule-table .routine-row {
            background: #fef9e7;
        }

        .schedule-table .main-row {
            background: #eaf2f8;
        }

        /* タグ */
        .tag-list {
            margin-bottom: 6px;
            font-size: 7.5pt;
        }

        .tag {
            display: inline-block;
            background: #e8ecef;
            padding: 1px 6px;
            border-radius: 2px;
            font-size: 7pt;
            margin-right: 3px;
            color: #555;
        }

        /* フッター */
        .footer {
            text-align: center;
            margin-top: 10px;
            padding-top: 4px;
            border-top: 0.5px solid #ccc;
            font-size: 6.5pt;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>活動支援案</h1>
        <div class="header-sub">放課後等デイサービス 活動計画書</div>
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
            <td>{{ $targetGradeLabel ?: '全学年' }}</td>
        </tr>
        <tr>
            <td class="meta-label">総活動時間</td>
            <td>{{ $plan->total_duration }}分</td>
            <td class="meta-label">曜日</td>
            <td>{{ $dayOfWeekLabel ?: '-' }}</td>
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
    <div class="section">
        <div class="section-title">活動の目的</div>
        <div class="section-content">{{ $plan->activity_purpose }}</div>
    </div>
    @endif

    {{-- 活動の内容 --}}
    @if ($plan->activity_content)
    <div class="section">
        <div class="section-title">活動の内容</div>
        <div class="section-content">{{ $plan->activity_content }}</div>
    </div>
    @endif

    {{-- 五領域への配慮 --}}
    @if ($plan->five_domains_consideration)
    <div class="section">
        <div class="section-title">五領域への配慮</div>
        <div class="section-content">{{ $plan->five_domains_consideration }}</div>
    </div>
    @endif

    {{-- 活動スケジュール --}}
    @if ($plan->activity_schedule && count($plan->activity_schedule) > 0)
    <div class="section">
        <div class="section-title">活動スケジュール</div>
        <table class="schedule-table">
            <thead>
                <tr>
                    <th style="width: 4%;">No</th>
                    <th style="width: 10%;">種別</th>
                    <th style="width: 22%;">活動名</th>
                    <th style="width: 8%;">時間</th>
                    <th style="width: 56%;">内容</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($plan->activity_schedule as $i => $item)
                @php $isRoutine = ($item['type'] ?? '') === 'routine'; @endphp
                <tr class="{{ $isRoutine ? 'routine-row' : 'main-row' }}">
                    <td style="text-align: center;">{{ $i + 1 }}</td>
                    <td style="text-align: center;">{{ $isRoutine ? '毎日の支援' : '主活動' }}</td>
                    <td>{{ $item['name'] ?? '' }}</td>
                    <td style="text-align: center;">{{ $item['duration'] ?? '' }}分</td>
                    <td>{{ $item['content'] ?? '' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- その他 --}}
    @if ($plan->other_notes)
    <div class="section">
        <div class="section-title">その他の注意点</div>
        <div class="section-content">{{ $plan->other_notes }}</div>
    </div>
    @endif

    <div class="footer">
        出力日時: {{ now()->format('Y/m/d H:i') }}
    </div>
</body>
</html>
