<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>支援案 - {{ $plan->activity_name }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'IPA Gothic', 'IPAGothic', 'Hiragino Kaku Gothic Pro', 'Noto Sans JP', sans-serif;
            font-size: 10pt;
            line-height: 1.5;
            color: #333;
        }

        /* ── ヘッダー ── */
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header h1 {
            font-size: 18pt;
            font-weight: 700;
            color: #1a1a1a;
            letter-spacing: 4pt;
            border-bottom: 3px double #1a1a1a;
            display: inline-block;
            padding-bottom: 4px;
        }
        .header-sub {
            font-size: 9pt;
            color: #888;
            margin-top: 4px;
        }

        /* ── メタ情報 ── */
        .meta {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        .meta td {
            padding: 5px 10px;
            font-size: 9.5pt;
            border: 1px solid #ccc;
            vertical-align: middle;
        }
        .meta .lbl {
            background: #f8f9fa;
            font-weight: 600;
            color: #555;
            width: 15%;
            white-space: nowrap;
        }

        /* ── タグ ── */
        .tags {
            margin-bottom: 12px;
        }
        .tag {
            display: inline-block;
            background: #e9ecef;
            color: #495057;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 8.5pt;
            margin: 0 4px 4px 0;
        }

        /* ── セクション ── */
        .section {
            margin-bottom: 14px;
            page-break-inside: avoid;
        }
        .section-head {
            font-size: 11pt;
            font-weight: 700;
            color: #2c3e50;
            border-left: 4px solid #3498db;
            padding: 4px 0 4px 10px;
            margin-bottom: 6px;
            background: #f8f9fa;
        }
        .section-body {
            font-size: 9.5pt;
            line-height: 1.6;
            padding: 8px 12px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            background: #fff;
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        /* ── スケジュール ── */
        .sched {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
            font-size: 9pt;
        }
        .sched th {
            background: #2c3e50;
            color: #fff;
            font-weight: 600;
            padding: 6px 8px;
            text-align: center;
            font-size: 8.5pt;
        }
        .sched td {
            border: 1px solid #dee2e6;
            padding: 5px 8px;
            vertical-align: top;
            line-height: 1.4;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .sched .routine { background: #fff8e1; }
        .sched .main    { background: #e3f2fd; }
        .sched .no-col  { text-align: center; width: 30px; }
        .sched .type-col { text-align: center; width: 70px; }
        .sched .time-col { text-align: center; width: 50px; }

        /* ── フッター ── */
        .footer {
            margin-top: 20px;
            text-align: right;
            font-size: 8pt;
            color: #aaa;
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>活動支援案</h1>
        <div class="header-sub">放課後等デイサービス 活動計画書</div>
    </div>

    {{-- メタ情報 --}}
    <table class="meta">
        <tr>
            <td class="lbl">活動名</td>
            <td>{{ $plan->activity_name }}</td>
            <td class="lbl">活動日</td>
            <td>{{ $plan->activity_date ? $plan->activity_date->format('Y年m月d日') : '' }}</td>
        </tr>
        <tr>
            <td class="lbl">種別</td>
            <td>{{ $planTypeLabel }}</td>
            <td class="lbl">対象学年</td>
            <td>{{ $targetGradeLabel ?: '全学年' }}</td>
        </tr>
        <tr>
            <td class="lbl">総活動時間</td>
            <td>{{ $plan->total_duration }}分</td>
            <td class="lbl">作成者</td>
            <td>{{ $plan->staff->full_name ?? '' }}</td>
        </tr>
    </table>

    {{-- タグ --}}
    @if ($plan->tags)
    <div class="tags">
        @foreach (explode(',', $plan->tags) as $tag)
            <span class="tag">{{ trim($tag) }}</span>
        @endforeach
    </div>
    @endif

    {{-- 活動の目的 --}}
    @if ($plan->activity_purpose)
    <div class="section">
        <div class="section-head">活動の目的</div>
        <div class="section-body">{{ $plan->activity_purpose }}</div>
    </div>
    @endif

    {{-- 活動の内容 --}}
    @if ($plan->activity_content)
    <div class="section">
        <div class="section-head">活動の内容</div>
        <div class="section-body">{{ $plan->activity_content }}</div>
    </div>
    @endif

    {{-- 五領域への配慮 --}}
    @if ($plan->five_domains_consideration)
    <div class="section">
        <div class="section-head">五領域への配慮</div>
        <div class="section-body">{{ $plan->five_domains_consideration }}</div>
    </div>
    @endif

    {{-- 活動スケジュール --}}
    @if ($plan->activity_schedule && count($plan->activity_schedule) > 0)
    <div class="section">
        <div class="section-head">活動スケジュール</div>
        <table class="sched">
            <thead>
                <tr>
                    <th class="no-col">No</th>
                    <th class="type-col">種別</th>
                    <th>活動名</th>
                    <th class="time-col">時間</th>
                    <th>内容</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($plan->activity_schedule as $i => $item)
                @php $isRoutine = ($item['type'] ?? '') === 'routine'; @endphp
                <tr class="{{ $isRoutine ? 'routine' : 'main' }}">
                    <td class="no-col">{{ $i + 1 }}</td>
                    <td class="type-col">{{ $isRoutine ? '毎日の支援' : '主活動' }}</td>
                    <td>{{ $item['name'] ?? '' }}</td>
                    <td class="time-col">{{ $item['duration'] ?? '' }}分</td>
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
        <div class="section-head">その他の注意点</div>
        <div class="section-body">{{ $plan->other_notes }}</div>
    </div>
    @endif

    <div class="footer">
        出力日時: {{ now()->format('Y/m/d H:i') }}
    </div>

</body>
</html>
